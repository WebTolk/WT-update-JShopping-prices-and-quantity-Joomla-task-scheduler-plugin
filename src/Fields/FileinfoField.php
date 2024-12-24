<?php
/**
 * @package    Task - WT Update JoomShopping prices and quantity
 * @version       1.1.0
 * @Author        Sergey Tolkachyov, https://web-tolk.ru
 * @copyright     Copyright (C) 2024 Sergey Tolkachyov
 * @license       GNU/GPL http://www.gnu.org/licenses/gpl-3.0.html
 * @since         1.0.0
 */

namespace Joomla\Plugin\Task\Wtupdatejshoppingpricesandquantity\Fields;

use Joomla\CMS\Form\Field\NoteField;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;

defined('_JEXEC') or die;


class FileinfoField extends NoteField
{

    protected $type = 'Fileinfo';

    public function getLabel()
    {
        $data           = $this->form->getData();
        $params         = new Registry($data->get('params'));
        $directory_name = \trim($params->get('folder', '')); 
        $file_name      = \trim($params->get('filename', ''));
        $import_path    = \implode(DIRECTORY_SEPARATOR, [JPATH_SITE, $directory_name, $file_name]);

        $html = '';
        $has_folder = true;
        $has_file = true;
        if ($directory_name)
        {

            // No directory
            if (!\is_dir(\dirname($import_path)))
            {
                $has_folder = false;
            }
            $folder_message = Text::_('PLG_WTUPDATEJSHOPPINGPRICESANDQUANTITY_FIELD_FILEINFO_FILE_INFO_FOLDER_MESSAGE_'.($has_folder ? 'SUCCESS' : 'ERROR')); //'Wrong path to file specified. ' . $import_path . ' is not a valid directory.';

            if (!\is_file($import_path))
            {
                $has_file = false;
            }
            $file_message = Text::_('PLG_WTUPDATEJSHOPPINGPRICESANDQUANTITY_FIELD_FILEINFO_FILE_INFO_FILE_MESSAGE_'.($has_file ? 'SUCCESS' : 'ERROR')); //'Wrong path to file specified. ' . $import_path . ' is not a valid directory.';

            $html = "</div>
				<div class='card w-100 shadow-sm border border-".(($has_folder && $has_file) ? 'success' : 'warning')."'>
				<div class='card-body'>
				<h5 class='h5'><span class='badge bg-success'><span class='icon icon-file m-0'></span></span> " . Text::_('PLG_WTUPDATEJSHOPPINGPRICESANDQUANTITY_FIELD_FILEINFO_FILE_INFO') . "</h5>
				<ul>
				    <li><span class='me-2'><span class='badge bg-info'>".Text::_('PLG_WTUPDATEJSHOPPINGPRICESANDQUANTITY_FIELD_FILEINFO_FILE_INFO_FOLDER').":</span> <code>".(\dirname($import_path))."</code> <span class='badge bg-".($has_folder ? 'success' : 'danger')."'>" . $folder_message . "</span></span></li>
    			    <li><span class='me-2'><span class='badge bg-info'>".Text::_('PLG_WTUPDATEJSHOPPINGPRICESANDQUANTITY_FIELD_FILEINFO_FILE_INFO_FILE')."</span> <code>".($import_path)."</code> <span class='badge bg-".($has_file ? 'success' : 'danger')."'>" . $file_message . "</span></span></li>
                </ul>
				</div>
				</div>
				<div>
				";
        }

        return $html;
    }

    /**
     * Method to get the field input markup for a spacer.
     * The spacer does not have accept input.
     *
     * @return  string  The field input markup.
     *
     * @since   1.7.0
     */
    protected function getInput()
    {
        return ' ';
    }

}
