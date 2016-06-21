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

    /**
     * @var array stores the current rules
     */
    protected $rules = array();

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
        $this->loadRules();

        $url = wl($ID, array('do' => 'admin', 'page' => 'submgr'), true, '&');

        if($INPUT->has('d')) {
            try {
                $new = $INPUT->arr('d');
                $this->addRule($new['item'], $new['type'], $new['members']);
                send_redirect($url);
            } catch(Exception $e) {
                msg($e->getMessage(), -1);
            }
        }

        if($INPUT->has('rm')) {
            try {
                $this->removeRule($INPUT->str('rm'));
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
        echo '<h2>'.$this->getLang('rules').'</h2>';

        echo '<table>';
        echo '<tr>';
        echo '<th>' . $this->getLang('item') . '</th>';
        echo '<th>' . $this->getLang('members') . '</th>';
        echo '<th>' . $this->getLang('type') . '</th>';
        echo '<th></th>';
        echo '</tr>';

        foreach($this->rules as $item => $data) {
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

        echo '<h2>'.$this->getLang('legend').'</h2>';

        echo '<form method="post" action="' . $url . '">';
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
     * loads the current rules
     */
    protected function loadRules() {
        $lines = confToHash(DOKU_CONF . 'subscription.rules');

        foreach($lines as $key => $val) {
            $val = preg_split('/\s+/', $val, 2);
            $val = array_map('trim', $val);
            $val = array_filter($val);
            $val = array_unique($val);
            $lines[$key] = $val;
        }

        $this->rules = $lines;
        ksort($this->rules);
    }

    /**
     * Add a new rule
     *
     * @param string $item page or namespace
     * @param string $type every|digest|list
     * @param string $members user/group list
     * @throws Exception
     */
    protected function addRule($item, $type, $members) {
        $isns = $this->cleanItem($item);
        $members = trim($members);
        if(!in_array($type, array('every', 'digest', 'list'))) {
            throw new Exception('bad subscription type');
        }
        if(!$isns && $type == 'list') {
            throw new Exception('list subscription is not supported for single pages');
        }
        if(!$item) {
            throw new Exception('no page or namespace given');
        }
        if(!$members) {
            throw new Exception('no users or groups given');
        }

        // remove existing (will be ignored if doesn't exist)
        $this->removeRule($item);

        $this->rules[$item] = array($type, $members);
        $this->writeRules();

        $this->applyRule($item, $type, $members);
    }

    /**
     * Removes an existing rule
     *
     * @param string $item page or namespace
     */
    protected function removeRule($item) {
        if(!isset($this->rules[$item])) return;

        list($type, $members) = $this->rules[$item];
        unset($this->rules[$item]);
        $this->writeRules();

        $this->ceaseRule($item, $type, $members);
    }

    /**
     * saves the current rules
     *
     * @return bool true on success
     */
    protected function writeRules() {
        $out = "# auto subscription rules\n";
        foreach($this->rules as $item => $data) {
            $out .= "$item\t$data[0]\t$data[1]\n";
        }

        return io_saveFile(DOKU_CONF . 'subscription.rules', $out);
    }

    /**
     * Applies a rule by subscribing all affected users
     *
     * @param string $item page or namespace
     * @param string $type every|digest|list
     * @param string $members user/group list
     * @throws Exception
     */
    protected function applyRule($item, $type, $members) {
        $users = $this->getAffectedUsers($members);

        $sub = new Subscription();
        foreach($users as $user) {
            $sub->add($item, $user, $type);
        }
    }

    /**
     * Removes a rule by unsubscribing all affected users
     *
     * @param string $item page or namespace
     * @param string $type every|digest|list
     * @param string $members user/group list
     * @throws Exception
     */
    protected function ceaseRule($item, $type, $members) {
        $users = $this->getAffectedUsers($members);

        $sub = new Subscription();
        foreach($users as $user) {
            $sub->remove($item, $user, $type);
        }
    }

    /**
     * Gets all users affected by a member string
     *
     * @param string $members comma separated list of users and groups
     * @return array
     */
    protected function getAffectedUsers($members) {
        /** @var DokuWiki_Auth_Plugin $auth */
        global $auth;

        $members = explode(',', $members);
        $members = array_map('trim', $members);
        $members = array_filter($members);
        $members = array_unique($members);

        // get all users directly specified or from specified groups
        $users = array();
        foreach($members as $one) {
            if(substr($one, 0, 1) == '@') {
                $found = $auth->retrieveUsers(0, 0, array('grps' => substr($one, 1)));
                $users = array_merge($users, array_keys($found));
            } else {
                $users[] = $one;
            }
        }

        $users = array_unique($users);

        return $users;
    }

    /**
     * Clean the item and return if it is a namespace
     *
     * @param string &$item reference to the item to clear
     * @return bool is the given item a namespace? (trailing colon)
     */
    protected function cleanItem(&$item) {
        $isns = false;
        $item = trim($item);
        if(substr($item, -1) == ':') {
            $isns = true;
        }
        $item = cleanID($item);
        if($isns) $item = "$item:";

        return $isns;
    }

}

// vim:ts=4:sw=4:et:
