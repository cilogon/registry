<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     0.2.9
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 */
namespace App\Controller;

use Cake\Core\Configure;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Cake\ORM\TableRegistry;
use Cake\View\Exception\MissingTemplateException;
use \App\Lib\Enum\SuspendableStatusEnum;

/**
 * Static content controller
 *
 * This controller will render views from templates/Pages/
 *
 * @link https://book.cakephp.org/4/en/controllers/pages-controller.html
 */
class PagesController extends AppController
{   
    /**
     * Perform Cake Model initialization.
     *
     * @since  COmanage Registry v5.0.0
     * @param  array  $config Configuration options passed to constructor
     */

    public function initialize(): void
    {
        parent::initialize();

        // Configure breadcrumb rendering
        $this->Breadcrumb->skipAll(['/^\/$/']);
    }

    /**
     * Displays a view
     *
     * @param array ...$path Path segments.
     * @return \Cake\Http\Response|null
     * @throws \Cake\Http\Exception\ForbiddenException When a directory traversal attempt.
     * @throws \Cake\View\Exception\MissingTemplateException When the view file could not
     *   be found and in debug mode.
     * @throws \Cake\Http\Exception\NotFoundException When the view file could not
     *   be found and not in debug mode.
     * @throws \Cake\View\Exception\MissingTemplateException In debug mode.
     */
    public function display(...$path): ?Response
    {
        if (!$path) {
            return $this->redirect('/');
        }
        if (in_array('..', $path, true) || in_array('.', $path, true)) {
            throw new ForbiddenException();
        }
        $page = $subpage = null;

        if (!empty($path[0])) {
            $page = $path[0];
        }
        if (!empty($path[1])) {
            $subpage = $path[1];
        }
        $this->set(compact('page', 'subpage'));

        try {
            return $this->render(implode('/', $path));
        } catch (MissingTemplateException $exception) {
            if (Configure::read('debug')) {
                throw $exception;
            }
            throw new NotFoundException();
        }

        return $this->render();
    }

    /**
     * Render a Mostly Static Page.
     * 
     * @since  COmanage Registry v5.1.0
     * @param  string   $coid   CO ID
     * @param  string   $name   MSP Name (slug)
     */

    public function show(string $coid, string $name) {
        // We use PagesController rather than MostlyStaticPagesController to avoid complexities
        // with PrimaryLink lookups. We use show() rather than render() because the latter is
        // used by Controller, and rather than display() so we don't confuse things by
        // redefining it. We render here rather than redirecting into the MSPController to
        // reduce URL bar thrashing.

        $MSPTable = TableRegistry::getTableLocator()->get("MostlyStaticPages");

        $msp = $MSPTable->find()
                        ->where([
                            'co_id'     => (int)$coid,
                            'name'      => $name,
                            'status'    => SuspendableStatusEnum::Active
                        ])
                        ->first();
        
        if(empty($msp)) {
            if($name == 'error-landing') {
                // error-landing should always exist, if not throw an error

                throw new NotFoundException();
            } else {
                $this->Flash->error(__d('error', 'notfound', $name));

                return $this->redirect("/$coid/error-landing");
            }
        }

        $this->set('vv_bc_skip', true); // this doesn't do anything?

        $this->set('vv_title', $msp->title);
        $this->set('vv_body', $msp->body);

        return $this->render('/MostlyStaticPages/display');
    }

    /**
     * Indicate whether this Controller will handle some or all authnz.
     * 
     * @since  COmanage Registry v5.0.0
     * @param  EventInterface   $event  Cake event, ie: from beforeFilter
     * @return string                   "no", "open", "authz", or "yes"
     */

    public function willHandleAuth(\Cake\Event\EventInterface $event): string {
        $request = $this->getRequest();
        $action = $request->getParam('action');

        // We only take over authz for display and show
        // (These are the only two actions we currently support, but better to require
        // an explicit action to add to this list)

        if(in_array($action, ['display', 'show'])) {
            return 'open';
        }

        return 'no';
    }
}
