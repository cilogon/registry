<?php
declare(strict_types=1);

namespace CoreEnroller\Test\TestCase\View\Cell;

use Cake\TestSuite\TestCase;
use CoreEnroller\View\Cell\EmailVerifiersCell;

/**
 * CoreEnroller\View\Cell\EmailVerifiersCell Test Case
 */
class EmailVerifiersCellTest extends TestCase
{
    /**
     * Request mock
     *
     * @var \Cake\Http\ServerRequest|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $request;

    /**
     * Response mock
     *
     * @var \Cake\Http\Response|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $response;

    /**
     * Test subject
     *
     * @var \CoreEnroller\View\Cell\EmailVerifiersCell
     */
    protected $EmailVerifiers;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->request = $this->getMockBuilder('Cake\Http\ServerRequest')->getMock();
        $this->response = $this->getMockBuilder('Cake\Http\Response')->getMock();
        $this->EmailVerifiers = new EmailVerifiersCell($this->request, $this->response);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->EmailVerifiers);

        parent::tearDown();
    }

    /**
     * Test display method
     *
     * @return void
     * @uses \CoreEnroller\View\Cell\EmailVerifiersCell::display()
     */
    public function testDisplay(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
