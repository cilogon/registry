<?php
/**
 * COmanage Registry Env Sources Table
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
 * @since         COmanage Registry v5.2.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

namespace OrcidSource\Model\Table;

use Cake\ORM\Table;
use Cake\Utility\Security;
use Cake\Validation\Validator;
use Cake\Event\EventInterface;

class OrcidTokensTable extends Table {
    use \App\Lib\Traits\ChangelogBehaviorTrait;
    use \App\Lib\Traits\LabeledLogTrait;
    use \App\Lib\Traits\PermissionsTrait;
    use \App\Lib\Traits\PrimaryLinkTrait;
    use \App\Lib\Traits\QueryModificationTrait;
    use \App\Lib\Traits\TableMetaTrait;
    use \App\Lib\Traits\UpsertTrait;
    use \App\Lib\Traits\ValidationTrait;

    // Cache of Table Models
    protected $tableCache = [];

    // Cache of the type map, for flat mode
    protected $typeCache = [];

    /**
     * Perform Cake Model initialization.
     *
     * @since  COmanage Registry v5.2.0
     * @param  array  $config Configuration options passed to constructor
     */

    public function initialize(array $config): void {
        // Timestamp behavior handles created/modified updates
        $this->addBehavior('Changelog');
        $this->addBehavior('Log');
        $this->addBehavior('Timestamp');

        // Define associations
        $this->belongsTo('OrcidSource.OrcidSources');
        $this->setDisplayField('orcid_identifier');
        $this->setPrimaryLink('orcid_source_id');

        $this->setPermissions([
            // Actions that operate over an entity (ie: require an $id)
            'entity' => [
                'delete' =>   ['platformAdmin', 'coAdmin'],
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
     * Perform actions while marshaling data, before validation.
     *
     * @param EventInterface $event Event
     * @param \ArrayObject $data Object data, in array format
     * @param \ArrayObject $options Entity save options
     * @since  COmanage Registry v5.2.0
     */

    public function beforeMarshal(EventInterface $event, \ArrayObject $data, \ArrayObject $options)
    {
        // Encryption logic
        $key = Security::getSalt();

        foreach (['id_token', 'access_token', 'refresh_token'] as $column) {
            if (!empty($data[$column])) {
                // Security::encrypt expects string, $key must be correct length for the cipher!
                $payload = base64_encode(Security::encrypt($data[$column], $key));

                // If updating, try to fetch existing stored value to compare
                $stored_key = '';
                if (!empty($data['id'])) {
                    $entity = $this->find()->select([$column])->where(['id' => $data['id']])->first();
                    if ($entity) {
                        $stored_key = $entity->{$column};
                    }
                }

                if ($stored_key !== $payload) {
                    $data[$column] = $payload;
                }
            }
        }

    }

    /**
     * Unencrypt a value previously encrypted using salt
     *
     * @param string $value
     *
     * @return false|string
     * @since  COmanage Registry v5.2.0
     */

    public function getUnencrypted(string $value): string|false
    {
        if(empty($value)) {
            return '';
        }
        return Security::decrypt(base64_decode($value), Security::getSalt());
    }


    /**
     * Set validation rules.
     *
     * @since  COmanage Registry v5.2.0
     * @param  Validator $validator Validator
     * @return Validator            Validator
     * @throws InvalidArgumentException
     * @throws RecordNotFoundException
     */

    public function validationDefault(Validator $validator): Validator {
        $schema = $this->getSchema();

        $validator->add('orcid_source_id', [
            'content' => ['rule' => 'isInteger']
        ]);
        $validator->notEmptyString('orcid_source_id');

        foreach(['orcid_identifier', 'access_token', 'id_token', 'refresh_token'] as $column) {
            $validator->add($column, [
                'content' => [
                    'rule' => 'validateNotBlank',
                    'provider' => 'table'
                ]
            ]);
        }
        $validator->notEmptyString('orcid_identifier');
        $validator->notEmptyString('access_token');
        $validator->allowEmptyString('id_token');
        $validator->allowEmptyString('refresh_token');

        return $validator;
    }
}