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

        if($this->getConf('applyonlogin')) {
            $controller->register_hook('AUTH_LOGIN_CHECK', 'AFTER', $this, 'handle_login');
        }
    }

    /**
     * Apply rules on a newly created user
     *
     * @param Doku_Event $event event object by reference
     * @param mixed $param not used
     * @return void
     */
    public function handle_auth_user_change(Doku_Event $event, $param) {
        if($event->data['type'] != 'create') return;

        /** @var helper_plugin_submgr $hlp */
        $hlp = plugin_load('helper', 'submgr');
        $hlp->runRules($event->data['params'][0], $event->data['params'][4]);
    }

    /**
     * Apply rules on a successful user login (only once per session)
     *
     * @param Doku_Event $event event object by reference
     * @param mixed $param not used
     * @return void
     */
    public function handle_login(Doku_Event $event, $param) {
        global $INPUT;

        if(!$event->result) return;
        if(isset($_SESSION[DOKU_COOKIE]['submgr'])) return;
        if(!$INPUT->server->str('REMOTE_USER')) return;
        global $USERINFO;

        /** @var helper_plugin_submgr $hlp */
        $hlp = plugin_load('helper', 'submgr');
        $hlp->runRules($INPUT->server->str('REMOTE_USER'), $USERINFO['grps']);
        $_SESSION[DOKU_COOKIE]['submgr'] = 1;
    }

}

// vim:ts=4:sw=4:et:
