<?php
/**
 * DokuWiki Plugin submgr (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <dokuwiki@cosmocode.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class action_plugin_submgr extends DokuWiki_Action_Plugin {

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(Doku_Event_Handler $controller) {

       $controller->register_hook('AUTH_USER_CHANGE', 'BEFORE', $this, 'handle_auth_user_change');

    }

    /**
     * [Custom event handler which performs action]
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return void
     */

    public function handle_auth_user_change(Doku_Event $event, $param) {
        /** @var helper_plugin_submgr $hlp */
        $hlp = plugin_load('helper', 'submgr');

        if($event->data['type'] == 'create') {
            $hlp->runRules($event->data['params'][0], $event->data['params'][4]);
        }

    }

}

// vim:ts=4:sw=4:et:
