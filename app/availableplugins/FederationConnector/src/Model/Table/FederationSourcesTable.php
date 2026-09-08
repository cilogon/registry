<?php
/**
 * COmanage Registry Federation Sources Table
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
 * @package       registry
 * @since         COmanage Registry v5.3.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

namespace FederationConnector\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;
use Cake\Utility\Xml;
use Cake\Validation\Validator;
use \App\Lib\Enum\LanguageEnum;
use \App\Lib\Enum\SuspendableStatusEnum;
use App\Model\Entity\OrganizationSource;
use \FederationConnector\Lib\Enum\ProtocolEnum;

class FederationSourcesTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\LabeledLogTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;

  // Metadata cache, for Sync operations.
  protected $mdcache = [];

  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.3.0
   * @param  array  $config Configuration options passed to constructor
   */
  
  public function initialize(array $config): void {
    $this->addBehavior('Changelog');
    $this->addBehavior('Log');
    $this->addBehavior('Timestamp');
    
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Configuration);
    
    // Define associations
    $this->belongsTo('OrganizationSources');
    $this->belongsTo('Servers');
    
    $this->hasManyPlugins([
      'Servers' => [
        [
          'targetModel' => 'FederationConnector.FederationSources'
        ]
      ]
    ]);

    $this->setDisplayField('server_id');
    
    $this->setPrimaryLink(['organization_source_id']);
    $this->setRequiresCO(true);
    
    $this->setAutoViewVars([
      'protocols' => [
        'type' => 'enum',
        'class' => 'FederationConnector.ProtocolEnum'
      ],
      'servers' => [
        'type' => 'select',
        'model' => 'Servers',
        'where' => ['plugin' => 'CoreServer.HttpServers']
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
        'add' =>      false, //['platformAdmin', 'coAdmin'],
        'index' =>    ['platformAdmin', 'coAdmin']
      ]
    ]);
  }

  /**
   * Make a request to the configured server.
   *
   * @since  COmanage Registry v5.3.0
   * @param  OrganizationSource $source    OS Entity with instantiated plugin configuration
   * @param  string             $entityId  Entity ID to request, or null for all IdPs
   * @return Response                      Result of request, as returned by HttpClient
   */

  protected function doRequest(
    OrganizationSource $source,
    ?string $entityID=null
  ): \Cake\Http\Client\Response {
    // Start by pulling our server configuration

    $server = $this->Servers->get($source->federation_source->server_id, contain: ['HttpServers']);

    $Client = $this->Servers->HttpServers->createHttpClient($server->http_server->id);

    $url = "";
    $options = [];

    if($source->federation_source->protocol == ProtocolEnum::MDQ
       || $source->federation_source->protocol == ProtocolEnum::MDQIC) {
      $options['headers'] = ['Content-Type' => 'application/samlmetadata+xml'];
      $url = "/entities";

      if($entityID) {
        $url .= "/" . urlencode($entityID);
      } elseif($source->federation_source->protocol == ProtocolEnum::MDQIC) {
        // CO-2883 InCommon implements an IdP Only extension that is not
        // part of the original IETF draft. We support it here because
        // our initial use case involves pulling InCommon metadata.

        $url .= "idps/all";
      } else {
        // Standard request for all entities
// XXX when we get here, it looks like we're going to run into memory exhaustion issues
//     loading the stream
      }
    } else {
      // else for File we were given the full path for the URL in the configuration
    }

    return $Client->get(url: $url, options: $options);
  }

  /**
   * Find a node in metadata, which might or might not be prefixed.
   *
   * @since  COmanage Registry v4.4.0
   * @param  array    $metadata   Array of parsed metadata
   * @param  string   $prefix     Prefix that might or might not be used
   * @param  string   $node       Name of top level node
   * @param  string   $node2      Name of child node
   * @return mixed                The contents of $metadata at the specified node, or null
   */

  protected function findMetadataEntry(
    array $metadata,
    string $prefix,
    string $node1, 
    ?string $node2=null
  ): mixed {
    if(!empty($metadata[$node1])) {
      if($node2) {
        return $this->findMetadataEntry($metadata[$node1], $prefix, $node2);
      } else {
        return $metadata[$node1];
      }
    } else {
      $p1 = $prefix . ":" . $node1;

      if(!empty($metadata[$p1])) {
        if($node2) {
          return $this->findMetadataEntry($metadata[$p1], $prefix, $node2);
        } else {
          return $metadata[$p1];
        }
      }
    }

    return null;
  }

  /**
   * Map a SAML language tag to a Registry language enumeration.
   *
   * @since  COmanage Registry 5.3.0
   * @param  string  $lang  SAML language tag
   * @return string|null    Registry language enumeration, or null
   */

  protected function mapLang(string $lang): ?string {
    // In general, SAML language tags and Registry language enumerations are the same,
    // but there are some exceptions. SAML tags are actually XML tags, which are defined
    // in RFC 3066. In practice, the metadata uses ISO-639 two letter language codes
    // (matching Registry), with some further using ISO-3166 two-letter country codes
    // (eg "en-US", and which match Registry as a substring).

    // As of the initial implementation, this is the complete set of languages found
    // in InCommon metadata:
    // bg, ca, cs, da, de, el, en, en-us, es, eu, fi, fr, gl, is, it, ja,
    // lt, nl, pl, pt, ro, sk, sr, sv, tr, zh

    $ret = $lang;

    if(strlen($ret)==5 && $ret[2]=='-') {
      // This is probably ll-CC, so just chop off the -CC

      $ret = substr($ret, 0, 2);
    }

    if(strlen($ret)==2) {
      // We just need to verify the language is defined in the enumeration.
      // (For UI purposes not all languages are defined out of the box.)
      // If not found, we simply return null.

      if(!in_array($ret, LanguageEnum::getConstValues())) {
        // Language not known, blank it out so saves validate
        $ret = null;
      }
    } else {
      // We don't know how to handle this string
      $ret = null;
    }

    return $ret;
  }

  /**
   * Populate the local cache by parsing the full metadata file.
   *
   * @since  COmanage Registry v5.3.0
   * @param  OrganizationSource $source   OS Entity with instantiated plugin configuration
   * @return int                          The number of records cached
   * @throws RuntimeException
   */

  protected function populateCache(OrganizationSource $source) {
    if(!empty($this->mdcache)) {
      return count($this->mdcache);
    }

    // This $response object uses over 100MB of memory(!), whereas the various
    // XML processors below (using XMLReader, which is more efficient) use only a
    // trivial amount. Each cache entry (array from resultToOrganization) uses about
    // 20kb, which once 100+MB have been allocated can easily push us over the max
    // limit, so we unset the $response as soon as we can dispose of it.
    // In aggregate, we'll use about 30MB to hold the cache.

    // Measurements based on memory_get_usage() reports and the InCommon MDQ aggregate,
    // which is about 55MB when downloaded.

    $response = $this->doRequest($source);

    if($response->getStatusCode() == 200) {
      //$XMLReader = \XMLReader::XML($response->getBody());
      // getBody() returns Laminas\Diactoros\Stream
      $XMLReader = \XMLReader::XML($response->getBody()->getContents());

      // We're done with this, claim back the memory before we populate the cache
      unset($response);

      $count = 0;

      while($XMLReader->read()) {
        // Node names might or might not be prefixed with a namespace label (eg: "md:")
        // depending on the server.

        if($XMLReader->nodeType == \XMLReader::END_ELEMENT) continue;

        if($XMLReader->name == 'EntityDescriptor' || $XMLReader->name == 'md:EntityDescriptor') {
          $metadata = Xml::toArray(Xml::build($XMLReader->readOuterXML()));

          $IDPSSODescriptor = $this->findMetadataEntry($metadata, 'md', 'EntityDescriptor', 'IDPSSODescriptor');

          if(empty($IDPSSODescriptor)) {
            // This is not an IdP, skip it
            continue;
          }

          if(!empty($metadata['EntityDescriptor']['@entityID'])) {
            // Note Xml will strip the md: namespace indicator when present, but only for
            // top level attributes in the array
            $entityID = $metadata['EntityDescriptor']['@entityID'];

            $this->mdcache[$entityID]['rec'] = $this->resultToOrganization($metadata['EntityDescriptor']);
            // We json_encode the result for consistency with search()
            $this->mdcache[$entityID]['raw'] = json_encode($this->mdcache[$entityID]['rec']);

            if(!empty($this->mdcache[$entityID])) {
              // print "Display Name: " . $this->mdcache[$entityID]['rec']['Organization']['name'] . "\n";
            }
          }

          $count++;
        }
      }

      return $count;
    } else {
      // XXX There might be more information in $response->body, we should bubble that up
      // or log it

      throw new \RuntimeException($response->getReasonPhrase());
    }
  }

  /**
   * Convert a record from the MDQ data to a record suitable for construction of
   * an Organization Entity.
   * 
   * @since  COmanage Registry v5.3.0
   * @param  array  $result   MDQ record
   * @return array            Entity record (in array format)
   */

  protected function resultToOrganization(array $result): array {
    // Build the Organization as an array for consistency with the EIS infrastructure
    $orgdata = [];

    // Use the entity ID as the source_key
    $ret['source_key'] = $result['@entityID'];

    $IDPSSODescriptorExt = $this->findMetadataEntry($result, 'md', 'IDPSSODescriptor', 'Extensions');

    // Note that the SAML Metadata UI spec (§2.4.3) says the following order of precedence SHOULD
    // be used for populating a display name:
    // <mdui:DisplayName>
    // <md:ServiceName> (if applicable, which it isn't here)
    // entityID or a hostname associated with the endpoint of the service
    // Implementations MAY support the use of <md:OrganizationDisplayName>, particularly as a
    // migration strategy, but this is not recommend this as a general practice.
    // Since we might also have OrganizationNames, we'll use that if DisplayName isn't available.

    // We first look for various attributes in the UI metadata. These can either be single valued
    // or multi, which are typically (but not always) differentiated via language tags.

    foreach([
      'name' => ['field' => 'mdui:DisplayName', 'maxlen' => 128],
      'description' => ['field' => 'mdui:Description', 'maxlen' => 128],
      'logo_url' => ['field' => 'mdui:Logo', 'maxlen' => 256]
    ] as $field => $src) {
      if(!empty($IDPSSODescriptorExt['mdui:UIInfo'][ $src['field'] ]['@'])) {
        // If there is just a single value, use that

        $ret[$field] = substr($IDPSSODescriptorExt['mdui:UIInfo'][ $src['field'] ]['@'], 0, $src['maxlen']);
      } elseif(!empty($IDPSSODescriptorExt['mdui:UIInfo'][ $src['field'] ])) {
        // We have multiple values, most likely flagged by language. We don't really have a good
        // pattern to follow here for a single valued attribute, so we'll start by looking for
        // an English entry, and if we don't find that we'll use the first one.

        foreach($IDPSSODescriptorExt['mdui:UIInfo'][ $src['field'] ] as $xv) {
          if(!empty($xv['@xml:lang']) && $xv['@xml:lang'] == 'en') {
            $ret[$field] = substr($xv['@'], 0, $src['maxlen']);
            break;
          }
        }

        if(empty($ret[$field])) {
          // No English entry found, use the first one

          $ret[$field] = substr($IDPSSODescriptorExt['mdui:UIInfo'][ $src['field'] ][0]['@'], 0, $src['maxlen']);
        }
      }

      if($field == 'logo_url' && !empty($ret[$field])) {
        // Some IdP put actual data in (as permitted by the spec), which for now we don't support

        if(strncmp($ret[$field], "data:", 4)==0) {
          unset($ret[$field]);
        }
      }
    }

    if(!empty($IDPSSODescriptorExt['shibmd:Scope']['@'])) {
      // For now we only support single value scopes since it's not clear what to do with
      // multiple values
      $ret['saml_scope'] = $IDPSSODescriptorExt['shibmd:Scope']['@'];

      // Try to guess an organization type from the scope. This isn't going to be perfect.

      if(preg_match('/(\.edu$|\.edu\.|\.ac\.)/', $ret['saml_scope'])) {
        // The OS interface expects types as labels, and then converts them to type_ids.
        $ret['type'] = 'edu';
      } elseif(preg_match('/(\.com^|\.com\.|\.co\.)/', $ret['saml_scope'])) {
        $ret['type'] = 'com';
      } elseif(preg_match('/(\.gov^|\.gov\.)/', $ret['saml_scope'])) {
        $ret['type'] = 'gov';
      }
      // else we don't know what to do, so we don't do anything
    }

    $ret['identifiers'][] = [
      'identifier'  => $result['@entityID'],
      'type'        => 'entityid',
      'status'      => SuspendableStatusEnum::Active
    ];

    // The SAML Metadata UI spec (§2.4.3) says use OrganizationDisplayName for display purposes,
    // but also that its use isn't recommended. For now we'll use OrganizationName, which
    // appears to be the more "official", though in most cases they're the same. (An example
    // where they differ is Penn State.)

    $OrganizationName = $this->findMetadataEntry($result, 'md', 'Organization', 'OrganizationName');

    if($OrganizationName && !empty($OrganizationName['@'])) {
      // There's only one OrganizatonName, push it one level down for compatibility with
      // processing multiple URLs.

      $OrganizationName = [$OrganizationName];
    }

    if(is_array($OrganizationName)) {
      // Multiple values, probably for different languages

      foreach($OrganizationName as $name) {
        $ret['identifiers'][] = [
          'identifier'  => $name['@'],
          'type'        => 'name',
          'status'      => SuspendableStatusEnum::Active,
          'language'    => $this->mapLang(isset($name['@xml:lang']) ? $name['@xml:lang'] : "")
        ];

        // If we didn't get a name from the UI metadata, use this one (this will effectively
        // pick the first in the array)
        if(empty($ret['name'])) {
          $ret['name'] = $name['@'];
        }
      }
    }

    $OrganizationURL = $this->findMetadataEntry($result, 'md', 'Organization', 'OrganizationURL');

    if(!empty($OrganizationURL['@'])) {
      // There's only one URL, push it one level down for compatibility with
      // processing multiple URLs.

      $OrganizationURL = [$OrganizationURL];
    }

    if(is_array($OrganizationURL)) {
      foreach($OrganizationURL as $url) {
        $ret['urls'][] = array(
          'url'         => $url['@'],
          'type'        => 'official',
          'language'    => $this->mapLang(isset($url['@xml:lang']) ? $url['@xml:lang'] : "")
        );
      }
    }

    // Contact People

    $ContactPerson = $this->findMetadataEntry($result, 'md', 'ContactPerson');

    if(!empty($ContactPerson['@contactType'])) {
      // There's only one Contact, push it one level down for compatibility with
      // processing multiple Contacts.

      $ContactPerson = [$ContactPerson];
    }

    if(!empty($ContactPerson)) {
      foreach($ContactPerson as $cp) {
        // Convert a SAML ContactPerson to a Registry Contact. EmailAddress and TelephoneNumber
        // are technically multi-valued, but few entities have multiple values, so for now we
        // just use the first one we see.
        $t = ['EmailAddress' => null, 'TelephoneNumber' => null];

        foreach(array_keys($t) as $k) {
          $info = $this->findMetadataEntry($cp, 'md', $k);

          if(!empty($info)) {
            if(is_array($info)) {
              $t[$k] = $info[0];
            } else {
              $t[$k] = $info;
            }
          }
        }

        // Email Addresses are specified as URIs in the SAML spec
        if(!empty($t['EmailAddress'])) {
          $parsed = parse_url($t['EmailAddress']);

          if(!empty($parsed['scheme']) && $parsed['scheme'] == 'mailto') {
            $t['EmailAddress'] = $parsed['path'];
          } else {
            // While EmailAddress is supposed to be a URI, not everyone correctly populates it
            //$t['EmailAddress'] = $cp['EmailAddress'];
          }
        }

        $ret['contacts'][] = [
          'given'   => !empty($cp['GivenName']) ? $cp['GivenName'] : (!empty($cp['md:GivenName']) ? $cp['md:GivenName'] : null),
          'family'  => !empty($cp['SurName']) ? $cp['SurName'] : (!empty($cp['md:SurName']) ? $cp['md:SurName'] : null),
          'company' => !empty($cp['Company']) ? $cp['Company'] : (!empty($cp['md:Company']) ? $cp['md:Company'] : null),
          'number'  => $t['TelephoneNumber'],
          'mail'    => $t['EmailAddress'],
          'type'    => !empty($cp['@contactType']) ? $cp['@contactType'] : null,
        ];
      };
    }

    // If we don't have a name at this point, use the entityID
    if(empty($ret['name'])) {
      $ret['name'] = $result['@entityID'];
    }
    
    return $ret;
  }

  /**
   * Retrieve a record from the Organization Source.
   * 
   * @since  COmanage Registry v5.3.0
   * @param  OrganizationSource $source     OS Entity with instantiated plugin configuration
   * @param  string             $source_key Backend source key for requested record
   * @return array                          Array of source_key, source_record, and entity_data
   * @throws InvalidArgumentException
   */

  public function retrieve(
    OrganizationSource $source,
    string $source_key
  ): array {
    // XXX do we need to urldecode $source_key? it looks like it already is...
    //debug($source_key);

    $ret = [
      'source_key' => $source_key,
      'source_record' => null,
      'entity_data' => null
    ];

    // Do we already have the record in the cache?
    if(!empty($this->mdcache[$source_key])) {
      return $this->mdcache[$source_key];
    }

    // Otherwise this is basically just search, which will throw InvalidArgumentException
    // on not found. 
    // XXX Is this still true? (as of right now it isn't) : Note we're passing the _un_decoded $key because search is going to
    // urldecode it.

    $r = $this->search($source, ['entityID' => $source_key]);

    $ret['source_record'] = $r[$source_key]['raw'];
    $ret['entity_data'] = $r[$source_key]['rec'];

    return $ret;
  }

  /**
   * Search the Organization Source.
   * 
   * @since  COmanage Registry v5.3.0
   * @param  OrganizationSource $source       OS Entity with instantiated plugin configuration
   * @param  array              $searchAttrs  Array of search attributes and values, as configured by searchAttributes()
   * @return array                            Array of matching records
   * @throws InvalidArgumentException
   */

  public function search(OrganizationSource $source, array $searchAttrs): array {
    if(empty($searchAttrs['entityID'])) {
      throw new \InvalidArgumentException(__d('federation_source', 'error.FederationSources.notprov.entityid'));
    }

    // $searchKey = urldecode($attributes['entityID']);
    $searchKey = $searchAttrs['entityID'];

    $ret = [];

    if($source->federation_source->protocol == ProtocolEnum::MDQ
       || $source->federation_source->protocol == ProtocolEnum::MDQIC) {
      $response = $this->doRequest($source, $searchKey);

      if($response->getStatusCode() == 200) {
        $metadata = Xml::toArray($response->getXml());

        if(empty($metadata['EntityDescriptor']['@entityID'])) {
          throw new \RuntimeException('er.federationsource.notfound.entityid');
        }

        $entityID = $metadata['EntityDescriptor']['@entityID'];

        $ret[$entityID]['rec'] = $this->resultToOrganization($metadata['EntityDescriptor']);

        // Because of differences between how the XML is processed here vs when cached via
        // preRunChecks, we can't use the raw XML here. Instead, we json_encode the
        // post-processed record.

        // Some records (eg https://idp.gbu.edu.in/idp/shibboleth) have invalid UTF-8 characters
        // in them. We could use JSON_INVALID_UTF8_IGNORE here, but then a save will fail
        // somewhere else, possible causing the job to abort.
        $ret[$entityID]['raw'] = json_encode($ret[$entityID]['rec']);
      } elseif($response->getStatusCode() == 404) {
        // Invalid entity ID
        throw new \InvalidArgumentException($response->getReasonPhrase());
      } else {
        // There might be more information in the response body, so we log it
        $this->llog('error', 'MDQ Error: ' . $response->getBody());
        throw new \RuntimeException($response->getReasonPhrase());
      }
    } else {
      // We have to retrieve and parse the entire file, so we'll use the cache
      $this->populateCache($source);

      // Since we only support searching on entityID (as per MDQ) we can just check
      // the cache (similar to retrieve).

      if(!empty($this->mdcache[$searchKey])) {
        $ret[$searchKey] = $this->mdcache[$searchKey];
      } else {
        throw new \InvalidArgumentException(__d('error', 'notfound', [$searchKey]));
      }
    }

    return $ret;
  }

  /**
   * Obtain the set of searchable attributes for this backend.
   * 
   * @since  COmanage Registry v5.3.0
   * @return array    Array of searchable attributes and localized descriptions
   */

  public function searchableAttributes(): array {
    // MDQ doesn't have a search interface, the only thing we can do is retrieve by Entity ID.

    return [
      'entityID' => __d('federation_connector', 'field.FederationSources.entityid.desc')
    ];
  }

  /**
   * Set validation rules.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Validator $validator Validator
   * @return Validator            Validator
   * @throws InvalidArgumentException
   * @throws RecordNotFoundException
   */
  
  public function validationDefault(Validator $validator): Validator {
    $schema = $this->getSchema();
    
    $validator->add('organization_source_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('organization_source_id');
    
    $validator->add('server_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('server_id');
    
    $validator->add('protocol', [
      'content' => ['rule' => ['inList', ProtocolEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('protocol');
   
    return $validator; 
  }
}