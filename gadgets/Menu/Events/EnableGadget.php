<?php
/**
 * Menu EnableGadget event
 *
 * @category    Gadget
 * @package     Menu
 * @author      Ali Fazelzadeh <afz@php.net>
 * @copyright   2013-2015 Jaws Development Group
 * @license     http://www.gnu.org/copyleft/lesser.html
 */
class Menu_Events_EnableGadget extends Jaws_Gadget_Event
{
    /**
     * Event execute method
     *
     */
    function Execute($shouter, $gadget)
    {
        $model = $this->gadget->model->loadAdmin('Menu');
        $res = $model->PublishGadgetMenus($gadget, true);
        return $res;
    }

}