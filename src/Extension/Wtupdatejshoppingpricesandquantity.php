<?php
/**
 * @package       WT update jshopping prices and quantity
 * @version       1.0.0
 * @Author        Sergey Tolkachyov, https://web-tolk.ru
 * @copyright     Copyright (C) 2024 Sergey Tolkachyov
 * @license       GNU/GPL http://www.gnu.org/licenses/gpl-3.0.html
 * @since         1.0.0
 */

namespace Joomla\Plugin\Task\Wtupdatejshoppingpricesandquantity\Extension;

use Joomla\CMS\Date\Date;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;


// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * A task plugin. For Delete Action Logs after x days
 * {@see ExecuteTaskEvent}.
 *
 * @since 5.0.0
 */
final class Wtupdatejshoppingpricesandquantity extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;
    use TaskPluginTrait;

    /**
     * @var string[]
     * @since 5.0.0
     */
    private const TASKS_MAP = [
        'plg_task_wtupdatejshoppingpricesandquantity' => [
            'langConstPrefix' => 'PLG_WTUPDATEJSHOPPINGPRICESANDQUANTITY',
            'method'          => 'Wtupdatejshoppingpricesandquantity',
            'form'            => 'wtupdatejshoppingpricesandquantity',
        ],
    ];
    /**
     * @var boolean
     * @since 5.0.0
     */
    protected $autoloadLanguage = true;
    /**
     * @var array|string[]
     * @since 1.0.0
     */
    private array $product_attr_field_name_map = [
        'product_ean'       => 'ean',
        'manufacturer_code' => 'manufacturer_code',
        'real_ean'          => 'real_ean',
    ];

    /**
     * @inheritDoc
     *
     * @return string[]
     *
     * @since 5.0.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList'    => 'advertiseRoutines',
            'onExecuteTask'        => 'standardRoutineHandler',
            'onContentPrepareForm' => 'enhanceTaskItemForm',
        ];
    }


    /**
     * @param   ExecuteTaskEvent  $event  The `onExecuteTask` event.
     *
     * @return integer  The routine exit code.
     *
     * @throws \Exception
     * @since  5.0.0
     */
    private function Wtupdatejshoppingpricesandquantity(ExecuteTaskEvent $event): int
    {
        /** @var Registry $params Current task params */
        $params = new Registry($event->getArgument('params'));
        /** @var int $task_id The task id */
        $task_id = $event->getTaskId();

        $directory_name = trim($params->get('folder')); 
        $file_name      = trim($params->get('filename')); 
        $import_path    = implode(DIRECTORY_SEPARATOR, [JPATH_SITE, $directory_name, $file_name]);
        // No directory
        if (!\is_dir(\dirname($import_path)))
        {
            $message = 'Wrong path to file specified. ' . $import_path . ' is not a valid directory.';
            $this->snapshot['output'] = $message;
            $this->logTask($message, 'error');

            return Status::KNOCKOUT;
        }
        // No file
        if (!\is_file($import_path))
        {
            $message = 'There is no such file: ' . $file_name . ' in ' . \dirname($import_path) . ' directory.';
            $this->snapshot['output'] = $message;
            $this->logTask(
                $message,
                'error'
            );

            return Status::KNOCKOUT;
        }

        $file_time_modified = (int)\filemtime($import_path);

        $task_last_run = $this->getTaskLustRunTime($task_id) ?? 1;

        if ($file_time_modified > $task_last_run)
        {
            if (!\file_exists(JPATH_SITE . '/components/com_jshopping/bootstrap.php'))
            {
				$message = 'JoomShopping component has not installed.';
                $this->snapshot['output'] = $message;
                $this->logTask($message, 'error');

                return Status::KNOCKOUT;
            }
            require_once(JPATH_SITE . '/components/com_jshopping/bootstrap.php');

            $jshopConfig              = \Joomla\Component\Jshopping\Site\Lib\JSFactory::getConfig();
            $jshop_attributes_enabled = $jshopConfig->admin_show_attributes;

            $csv = new \Joomla\Component\Jshopping\Site\Lib\Csv();
            $delimiter = \trim($params->get('columns_delimiter', ';'));
            $csv->setDelimit($delimiter);
            $data = $csv->read($import_path);
            if ($params->get('first_row_are_headers', false))
            {
                // первый ряд - заголовки столбцов
                \array_shift($data);
            }
            /** @var string $field_name JoomShopping product/attrs table column name */
            $field_name = $params->get('identifier_field_name', 'product_ean');

            foreach ($data as $product)
            {
                if (empty($product[0]))
                {
                    continue;
                }
                $product = \array_map('trim', $product);

                $this->updateProductData($params, $field_name, $product[0], $product[1], $product[2] );

                if ($jshop_attributes_enabled && $params->get('update_product_attributes', false))
                {
                    $this->updateProductAttrData($params, $field_name, $product[0], $product[1], $product[2] );
                }
            }
        }
        $this->snapshot['output'] = \count($data).' rows in file '.$import_path.' has been processed';
        return Status::OK;
    }

    /**
     * Returns a Unix timestamp of last task execution
     *
     * @param $task_id
     *
     * @return int
     *
     * @since 1.0.0
     */
    private function getTaskLustRunTime($task_id)
    {
        $taskModel = $this->getApplication()
                          ->bootComponent('com_scheduler')
                          ->getMVCFactory()
                          ->createModel(
                              'Task',
                              'Administrator',
                              ['ignore_request' => true]
                          );
        $task      = $taskModel->getItem($task_id);
        if($task->last_execution)
        {
            $last_run  = (new Date($task->last_execution))->toUnix();
        } else {
            $last_run = 1;
        }

        return $last_run;
    }

    /**
     *
     * @param   Registry         $params     Task params
     * @param   string           $field      primary key for product search in dtabase
     * @param   string           $condition  primary key value
     * @param ?string|float|int  $product_quantity
     * @param ?string|float|int  $product_price
     *
     * @since 1.0.0
     */
    private function updateProductData(
        $params,
        $field = 'product_ean',
        $condition = '',
        $product_quantity = null,
        $product_price = null
    ) {
        $result = false;
        if (empty($condition))
        {
            return $result;
        }

        $db          = $this->getDatabase();
        $query       = $db->getQuery(true);
        $jshopHelper = new \Joomla\Component\Jshopping\Site\Helper\Helper();
        $data        = [];
        if (!empty($product_price) && $params->get('update_prices', false))
        {
            $data[] = $db->quoteName('product_price') . ' = ' . $db->quote($jshopHelper::formatEPrice($product_price));
        }

        if (!empty($product_quantity)  && $params->get('update_quantity', false))
        {
            $data[] = $db->quoteName('product_quantity') . ' = ' . $db->quote(
                    $jshopHelper::formatqty($product_quantity)
                );
        }

        if (\count($data) > 0)
        {
            $conditionals = [
                $db->quoteName($field) . ' = ' . $db->quote($condition),
            ];

            $query->update($db->quoteName('#__jshopping_products'))
                  ->set($data)
                  ->where($conditionals);

           $result = $db->setQuery($query)->execute();
        }

        return $result;
    }

    /**
     *
     * @param   Registry         $params     Task params
     * @param   string           $field      primary key for product search in dtabase
     * @param   string           $condition  primary key value
     * @param ?string|float|int  $product_quantity
     * @param ?string|float|int  $product_price
     *
     * @since 1.0.0
     */
    private function updateProductAttrData(
        $params,
        $field = 'product_ean',
        $condition = '',
        $product_quantity = null,
        $product_price = null
    ) {
        $result = false;
        if (empty($condition))
        {
            return $result;
        }

        $field = ($field == 'product_ean') ? 'ean' : $field;

        $db          = $this->getDatabase();
        $query       = $db->getQuery(true);
        $jshopHelper = new \Joomla\Component\Jshopping\Site\Helper\Helper();
        $data        = [];
        if (!empty($product_price) && $params->get('update_prices', false))
        {
            $data[] = $db->quoteName('price') . ' = ' . $db->quote($jshopHelper::formatEPrice($product_price));
        }

        if (!empty($product_quantity) && $params->get('update_quantity', false))
        {
            $data[] = $db->quoteName('count') . ' = ' . $db->quote($jshopHelper::formatqty($product_quantity));
        }

        if (\count($data) > 0)
        {
            $conditionals = [
                $db->quoteName($field) . ' = ' . $db->quote($condition),
            ];

            $query->update($db->quoteName('#__jshopping_products_attr'))
                  ->set($data)
                  ->where($conditionals);
            $result = $db->setQuery($query)->execute();
        }
        return $result;
    }
}
