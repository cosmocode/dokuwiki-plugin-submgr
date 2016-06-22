<?php
/**
 * DokuWiki Plugin submgr (Admin Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <dokuwiki@cosmocode.de>
 */

// must be run within Dokuwiki

if(!defined('DOKU_INC')) die();

class admin_plugin_submgr extends DokuWiki_Admin_Plugin {

    /** @var helper_plugin_submgr */
    protected $hlp;

    /**
     * admin_plugin_submgr constructor.
     */
    public function __construct() {
        $this->hlp = plugin_load('helper', 'submgr');
    }

    /**
     * @return bool true if only access for superuser, false is for superusers and moderators
     */
    public function forAdminOnly() {
        return false;
    }

    /**
     * Should carry out any processing required by the plugin.
     */
    public function handle() {
        global $INPUT;
        global $ID;

        $url = wl($ID, array('do' => 'admin', 'page' => 'submgr'), true, '&');

        if($INPUT->has('d')) {
            try {
                $new = $INPUT->arr('d');
                $this->hlp->addRule($new['item'], $new['type'], $new['members']);
                send_redirect($url);
            } catch(Exception $e) {
                msg($e->getMessage(), -1);
            }
        }

        if($INPUT->has('rm')) {
            try {
                $this->hlp->removeRule($INPUT->str('rm'));
                send_redirect($url);
            } catch(Exception $e) {
                msg($e->getMessage(), -1);
            }
        }

    }

    /**
     * Render HTML output, e.g. helpful text and a form
     */
    public function html() {
        global $ID;

        $url = wl($ID, array('do' => 'admin', 'page' => 'submgr'));

        echo $this->locale_xhtml('intro');


        if(!$this->checkSettings()) return;


        echo '<h2>' . $this->getLang('rules') . '</h2>';

        echo '<table>';
        echo '<tr>';
        echo '<th>' . $this->getLang('item') . '</th>';
        echo '<th>' . $this->getLang('members') . '</th>';
        echo '<th>' . $this->getLang('type') . '</th>';
        echo '<th></th>';
        echo '</tr>';

        foreach($this->hlp->getRules() as $item => $data) {
            echo '<tr>';
            echo '<td>' . hsc($item) . '</td>';
            echo '<td>' . hsc($data[1]) . '</td>';
            echo '<td>' . $this->getLang($data[0]) . '</td>';
            echo '<td>';

            echo '<form method="post" action="' . $url . '">';
            echo '<input type="hidden" name="rm" value="' . hsc($item) . '" />';
            echo '<button type="submit">' . $this->getLang('remove') . '</button>';
            echo '</form>';

            echo '</td>';
            echo '</tr>';
        }

        echo '</table>';

        echo '<h2>' . $this->getLang('legend') . '</h2>';

        echo '<form method="post" action="' . $url . '" class="plugin_submgr">';
        echo '<fieldset>';
        echo '<label class="block"><span>' . $this->getLang('item') . '</span> <input type="text" name="d[item]" /></label>';
        echo '<label class="block"><span>' . $this->getLang('members') . '</span> <input type="text" name="d[members]" /></label>';
        echo '<label class="block"><span>' . $this->getLang('type') . '</span>
                <select name="d[type]">
                    <option value="every">' . $this->getLang('every') . '</option>
                    <option value="digest">' . $this->getLang('digest') . '</option>
                    <option value="list">' . $this->getLang('list') . '</option>
                </select>
              </label>';
        echo '<button type="submit">' . $this->getLang('add') . '</button>';
        echo '</fieldset>';
        echo '</form>';

        echo $this->locale_xhtml('help');

    }

    /**
     * Check capabilities and print errors
     *
     * @return bool
     */
    protected function checkSettings() {
        /** @var DokuWiki_Auth_Plugin $auth */
        global $auth;
        $ok = true;

        if(!actionOK('subscribe')) {
            echo $this->locale_xhtml('nosubs');
            $ok = false;
        }

        if(!$auth->canDo('getUsers')) {
            echo $this->locale_xhtml('nousers');
            $ok = false;
        }

        return $ok;
    }
}

// vim:ts=4:sw=4:et:
