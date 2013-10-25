<?php
/**
 * Friend Admin Gadget
 *
 * @category   GadgetAdmin
 * @package    Friend
 * @author     Jonathan Hernandez <ion@suavizado.com>
 * @author     Pablo Fischer <pablo@pablo.com.mx>
 * @copyright  2004-2013 Jaws Development Group
 * @license    http://www.gnu.org/copyleft/gpl.html
 */
class Friends_AdminAction extends Jaws_Gadget_Action
{
    /**
     * Creates and prints the administration template
     *
     * @access  public
     * @return  string  XHTML template content
     */
    function Admin()
    {
        $gadgetHTML = $this->gadget->loadAdminAction('Friends');
        return $gadgetHTML->Friends();
    }
}