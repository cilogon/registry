<?php
/**
 * COmanage Registry Format Assigners Table
 *
 * Portions licensed to the University Corporation for Advanced Internet
 * Development, Inc. ("UCAID") under one or more contributor license agreements.
 * See the NOTICE file distributed with this work for additional information
 * regarding copyright ownership.
 *
 * UCAID licenses this file to you under the Apache License, Version 2.0
 * (the "License"); you may not use this file except in compliance with the
 * License. You may obtain a copy of the License at:
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @link          https://www.internet2.edu/comanage COmanage Project
 * @package       registry-plugins
 * @since         COmanage Registry v5.0.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types=1);

namespace CoreAssigner\Model\Table;

use Cake\Datasource\ConnectionManager;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

use CoreAssigner\Lib\Enum\CollisionModeEnum;
use CoreAssigner\Lib\Enum\PermittedCharactersEnum;

class FormatAssignersTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;

  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array  $config Configuration options passed to constructor
   */

  public function initialize(array $config): void {
    parent::initialize($config);

    $this->addBehavior('Changelog');
    $this->addBehavior('Log');
    // Timestamp behavior handles created/modified updates
    $this->addBehavior('Timestamp');

    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Configuration);

    // Define associations
    $this->belongsTo('IdentifierAssignments');

    $this->hasMany('CoreAssigner.FormatAssignerSequences')
         ->setDependent(true)
         ->setCascadeCallbacks(true);

    $this->setDisplayField('format');

    $this->setPrimaryLink('identifier_assignment_id');
    $this->setRequiresCO(true);

    $this->setAutoViewVars([
      'collisionModes' => [
        'type' => 'enum',
        'class' => 'CoreAssigner.CollisionModeEnum'
      ],
      'permittedCharacters' => [
        'type' => 'enum',
        'class' => 'CoreAssigner.PermittedCharactersEnum'
      ]
    ]);

    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>   false, // Delete the pluggable object instead
        'edit' =>     ['platformAdmin', 'coAdmin'],
        'view' =>     ['platformAdmin', 'coAdmin']
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
// XXX do we need to fix this for other plugin entry point models?
        'add' =>      false, // This is added by the parent model
        'index' =>    ['platformAdmin', 'coAdmin']
      ]
    ]);
  }

  /**
   * Assign an identifier.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  IdentifierAssignment $ia     Identifier Assignment describing the requested configuration
   * @param  object               $entity The entity (Person, Group, Department) to assign an Identifier for
   * @return string                       The newly proposed Identifier
   * @throws InvalidArgumentException
   * @throws RuntimeException
   */

  public function assign($ia, $entity): string {
    // Generate the new identifier. This requires several steps. First, substitute
    // non-collision number parameters to generate our base. If substituteParameters()
    // fails, it'll throw an Exception that we let bubble up.
    $base = $this->substituteParameters(
      $entity,
      // If no format is specified, default to "(#)".
      $ia->format_assigner->format ?? "(#)",
      $ia->format_assigner->permitted_characters
    );

    // Now that we've got our base, loop until we get a unique identifier.
    // We try a maximum of 10 (0 through 9) times, and track identifiers we've
    // seen already.
    
    $tested = [];
    $ret = null;
    
    // PAR-FormatAssigner-1 A maximum of 10 attempts will be made to assign an Identifier.
    for($i = 0;$i < 10;$i++) {
      $sequenced = $this->selectSequences(
        $base,
        $i,
        $ia->format_assigner->permitted_characters
      );
      
      // There may or may not be a collision number format. If so, we should end
      // up with a unique candidate (though for random it's possible we won't).
      $candidate = $this->assignCollisionNumber(
        $ia->format_assigner->id,
        $sequenced,
        $ia->format_assigner->collision_mode,
        $ia->format_assigner->minimum ?? 0,
        $ia->format_assigner->maximum
      );
      
      if(!in_array($candidate, $tested)
          // Also check that we didn't get an empty string
          && trim($candidate) != false) {
        // We have a new candidate (ie: one that wasn't generated on a previous loop),
        // so let's see if it is already in use.
        
        try {
          $this->IdentifierAssignments->checkAvailability(
            (!empty($ia->identifier_type_id) ? 'Identifiers' : 'EmailAddresses'),
            (!empty($ia->identifier_type_id) ? $ia->identifier_type_id : $ia->email_address_type_id),
            $candidate,
            $entity
          );

          $ret = $candidate;
        }
        catch(\OverflowException $e) {
          // The Identifier we generated is in use, try again
          $this->llog('trace', "Generated candidate identifier $candidate, but it is already in use");
        }
        
        if($ret) {
          break;
        }
        
        // else try the next one
        $tested[] = $candidate;
      }
    }

    if(!$ret) {
      throw new \RuntimeException(__d('error', 'IdentifierAssignments.failed'));
    }

    return $ret;
  }

  /**
   * Assign a collision number if the current identifier segment accepts one.
   *
   * @since  COmanage Registry v5.0.0
   * @param  int                $formatAssignerId Format Assigner ID
   * @param  string             $sequenced        Sequenced string as returned by selectSequences()
   * @param  CollisionModeEnum  $collisionMode    Collision number assignment mode
   * @param  int                $min              Minimum number to assign
   * @param  int                $max              Maximum number to assign (for Random mode only)
   * @return string                               Candidate string, possibly with a collision number assigned
   * @throws InvalidArgumentException
   */
  
  protected function assignCollisionNumber(
    int $formatAssignerId,
    string $sequenced,
    string $collisionMode,
    int $min,
    ?int $max=null
  ): string {
    // We expect $sequenced to be %s and not %d in order to be able to ensure
    // a specific width (ie: padded and/or truncated). This also makes sense in that
    // identifiers are really strings, not numbers.
    
    $matches = [];
    
    if(preg_match('/\%[0-9.]*s/', $sequenced, $matches)) {
      switch($collisionMode) {
        case CollisionModeEnum::Random:
          // Simply pick a number between $min and $max.

          $lmax = $max;
          
          if(!$max) {
            // We have to be a bit careful with min and max vs mt_rand(). substituteParameters()
            // will generate something like (%05.5s). If no explicit $max is configured by the
            // admin, we used mt_getrandmax. However, that could generate a string like 172500398.
            // We take the first (eg) 5 digits, which are "17250". If $min is 20000, we'll
            // incorrectly assign a collision number outside the permitted range (CO-1933).
            
            // Pull the width out of the string
            $width = (int)rtrim(ltrim(strstr($matches[0], '.'), "."), "s");
            
            // And calculate a new max
            $lmax = (10 ** $width) - 1;
          }

          $n = random_int($min, $lmax);
          return sprintf($sequenced, $n);
          break;
        case CollisionModeEnum::Sequential:
          return sprintf($sequenced, $this->FormatAssignerSequences->next(
                                       formatAssignerId: $formatAssignerId,
                                       affix: $sequenced,
                                       start: $min));
          break;
        default:
          throw new InvalidArgumentException(__d('error', 'unknown', $algorithm));
          break;
      }
    } else {
      // Nothing to do, just return the same string
      
      return $sequenced;
    }
  }

  /**
   * Select the sequenced segments to be processed for the given iteration.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string                   $base       Base string as returned by substituteParameters
   * @param  int                      $iteration  Iteration number (between 0 and 9)
   * @param  PermittedCharactersEnum  $permitted  Acceptable characters for substituted parameters
   * @return string                               Format with sequenced segments selected
   */
  
  protected function selectSequences(
    string $base, 
    int $iteration, 
    string $permitted
  ): string {
    $sequenced = "";
    
    // Loop through the string
    for($j = 0;$j < strlen($base);$j++) {
      switch($base[$j]) {
        case '\\':
          // Copy the next character directly
          if($j+1 < strlen($base)) {
            $j++;
            $sequenced .= $base[$j];
          }
          break;
        case '[':
          // Sequenced segment
          
          // Single Use segments are only incorporated into the specified iteration,
          // vs Additive segments that are incorporated into all subsequent ones as well.
          $singleuse = false;
          
          if($j+1 < strlen($base) && $base[$j+1] == '=') {
            $singleuse = true;
            $j++;
          }
          
          if($j+3 < strlen($base)) {
            $j++;
            
            if(($singleuse && ($base[$j] == $iteration))
                ||
                (!$singleuse && ($base[$j] <= $iteration))) {
              // This segment is now in effect, copy until we see a close bracket
              // (and jump past the ':')
              $j += 2;
              
              // Assemble the text for this segment. If after parameter substitution
              // we end up with no permitted characters, skip this segment
              
              $segtext = "";
              
              while($base[$j] != ']') {
                $segtext .= $base[$j];
                $j++;
              }
              
              if(strlen($segtext) > 0
                 && preg_match('/'. PermittedCharactersEnum::getPermittedCharacters($permitted) . '/', $segtext)) {
                $sequenced .= $segtext;
              }
            } else {
              // Move to end of segment, we're not using this one yet
              
              while($base[$j] != ']') {
                $j++;
              }
            }
          }
          break;
        default:
          // Just copy this character
          $sequenced .= $base[$j];
          break;
      }
    }
    
    return $sequenced;
  }

  /**
   * Perform parameter substitution on an identifier format to generate the base
   * string used in identifier assignment.
   *
   * @since  COmanage Registry v5.0.0
   * @param  EntityInterface          $entity     Entity to assign Identifier for
   * @param  string                   $format     Identifier assignment format
   * @param  PermittedCharactersEnum  $permitted  Acceptable characters for substituted parameters
   * @return string                               Identifier with paramaters substituted
   * @throws RuntimeException
   */
  
  protected function substituteParameters(
    $entity,
    string $format,
    string $permitted
  ): string {
    $base = "";
    
    // For random letter generation ('h', 'r', 'R')
    $randomCharSet = array(
      'h' => "0123456789abcdef",
      'l' => "abcdefghijkmnopqrstuvwxyz",  // Note no "l"
      'L' => "ACDEFGHIJKLMNPQPTUVWXYZ"    // Note no "B", "O", or "S" (similar to 8,0,5)
    );
    
    // Loop through the format string
    for($i = 0;$i < strlen($format);$i++) {
      switch($format[$i]) {
        case '\\':
          // Copy the next character directly
          if($i+1 < strlen($format)) {
            $i++;
            $base .= $format[$i];
          }
          break;
        case '(':
          // Parameter to substitute
          if($i+2 < strlen($format)) {
            // Move past '('
            $i++;
            
            $width = "";
            
            // Check if the next character is a width specifier
            if($format[$i+1] == ':') {
              // Don't advance $i yet since we still need it, so use $j instead
              for($j = $i+2;$j < strlen($format);$j++) {
                if($format[$j] != ')') {
                  $width .= $format[$j];
                } else {
                  break;
                }
              }
            }
            
            // Do the actual parameter replacement, blocking out characters that aren't permitted
            
            $charregex = '/'. PermittedCharactersEnum::getPermittedCharacters(enum: $permitted, invert: true) . '/';
            
            switch($format[$i]) {
              case 'f':
                $base .= sprintf("%.".$width."s",
                                 preg_replace($charregex, '', strtolower($entity->primary_name->family)));
                break;
              case 'F':
                $base .= sprintf("%.".$width."s",
                                 preg_replace($charregex, '', $entity->primary_name->family));
                break;
              case 'g':
                $base .= sprintf("%.".$width."s",
                                 preg_replace($charregex, '', strtolower($entity->primary_name->given)));
                break;
              case 'G':
                $base .= sprintf("%.".$width."s",
                                 preg_replace($charregex, '', $entity->primary_name->given));
                break;
              // Note 'h' is defined with 'l', below
              // case 'h':
              case 'I':
                // We skip the next character (a slash) and then continue reading
                // until we get to a close parenthesis
                $identifierType = "";
                
                $i+=2;
                
                while($format[$i] != ')' && $i < strlen($format)) {
                  $identifierType .= $format[$i];
                  $i++;
                }
                
                // Rewind one character because we're going to advance past it
                // again below.
                $i--;
                
                if($identifierType == "") {
                  throw new \RuntimeException(__d('error', 'IdentifierAssignments.type.none'));
                }

                // If we find more than one identifier of the same type, we
                // arbitrarily pick the first. We should be able to use Hash
                // to do this, but our type label appears to be one level too
                // deep. (The alternative would be to use Types->getTypeId but
                // then that adds another database call.)
                
                $id = null;

                foreach($entity->identifiers as $idx) {
                  if($idx->type->value == $identifierType) {
                    $id = $idx->identifier;
                    break;
                  }
                }
                
                if(empty($id)) {
                  throw new \RuntimeException(__d('error', 'IdentifierAssignments.type.notfound', $identifierType));
                }
                
                $base .= sprintf("%.".$width."s",
                                 preg_replace($charregex, '', $id));
                break;
              case 'h':
              case 'l':
              case 'L':
                for($j = 0;$j < ($width != "" ? $width : 1);$j++) {
                  $base .= $randomCharSet[ $format[$i] ][ mt_rand(0, strlen($randomCharSet[ $format[$i] ])-1) ];
                }
                break;
              case 'm':
                $base .= sprintf("%.".$width."s",
                                 preg_replace($charregex, '', strtolower( $entity->primary_name->middle)));
                break;
              case 'M':
                $base .= sprintf("%.".$width."s",
                                 preg_replace($charregex, '',  $entity->primary_name->middle));
                break;
              case 'n':
                $base .= sprintf("%.".$width."s",
                                 preg_replace($charregex, '', strtolower($entity->name)));
                break;
              case 'N':
                $base .= sprintf("%.".$width."s",
                                 preg_replace($charregex, '', $entity->name));
                break;
              case '#':
                // Convert the collision number parameter to a sprintf style specification,
                // left padded with 0s. Note that assignCollisionNumber expects %s, not %d.
                $base .= "%" . ($width != "" ? ("0" . $width . "." . $width) : "") . "s";
                break;
            }
            
            // Move past the width specifier
            if($width != "") {
              $i += strlen($width) + 1;
            }
            
            // Move past the ')'
            $i++;
          }
          break;
        default:
          // Just copy this character
          $base .= $format[$i];
          break;
      }
    }
    
    return $base;
  }

  /**
   * Set validation rules.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Validator $validator Validator
   * @return Validator            Validator
   */

  public function validationDefault(Validator $validator): Validator {
    $schema = $this->getSchema();

    $validator->add('identifier_assignment_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('identifier_assignment_id');

    $this->registerStringValidation($validator, $schema, 'format', true);

    $validator->add('minimum', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('minimum');

    $validator->add('maximum', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('maximum');

    $validator->add('collision_mode', [
      'content' => ['rule' => ['inList', CollisionModeEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('collision_mode');

    $validator->add('permitted_characters', [
      'content' => ['rule' => ['inList', PermittedCharactersEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('permitted_characters');

    return $validator;
  }
}
