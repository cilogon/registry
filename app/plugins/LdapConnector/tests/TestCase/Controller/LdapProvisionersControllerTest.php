<?php
declare(strict_types=1);

namespace LdapConnector\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use LdapConnector\Controller\LdapProvisionersController;

/**
 * LdapConnector\Controller\LdapProvisionersController Test Case
 *
 * @link \LdapConnector\Controller\LdapProvisionersController
 */
class LdapProvisionersControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * Fixtures
     *
     * @var list<string>
     */
    protected array $fixtures = [
        'plugin.LdapConnector.LdapProvisioners',
    ];
}
