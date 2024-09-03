<?php

/**
 * Usage This Class in `admin_init` Hook in WordPress
 */
class Import_Excel_Widget
{

    public $args = [];

    public function __construct($args)
    {
        $this->setup_variable($args);
        $this->sanitize_args();
        $this->setup_option_name();
        $this->setup_ajax_rewrite();
        $this->setup_widget_dashboard();
        $this->setup_ajax();
    }

    public function setup_variable($args)
    {
        $defaults = [
            'priority' => 999,
            'id' => '',
            'title' => '',
            'template' => WP_Auto_Parent_Terms::$plugin_path . '/widget-import-excel/html.php',
            'max_file_size' => 10,
            'html' => '<p>Html Content ..</p>',
            'capability' => 'manage_options',
            'number_per_process' => 30,
            'get_excel_content' => function ($array, $args) {
                return [
                    'status' => true,
                    'message' => '',
                    'list' => $array,
                    'table' => [
                        'Title' => 0
                    ]
                ];
            },
            'process_item' => function ($item, $key) {
            },
            'after_process_items' => function ($items) {
            },
            'after_completed_process' => function () {
            },
            'table' => [
                'thead' => ['عنوان', 'مقدار']
            ],
            'require_excel_file' => true
        ];
        $this->args = wp_parse_args($args, $defaults);
    }

    public function sanitize_args()
    {
        $this->args['id'] = str_ireplace("-", "_", $this->args['id']);
    }

    public function setup_ajax_rewrite()
    {
        $this->args['action'] = 'wordpress_admin_import_excel_' . $this->args['id'];
    }

    public function setup_option_name()
    {
        $user_id = get_current_user_id();
        $this->args['option_name'] = $this->args['id'] . '_excel_' . $user_id;
    }

    public function setup_widget_dashboard()
    {
        add_action('wp_dashboard_setup', [$this, 'wp_hook_dashboard_setup'], $this->args['priority']);
    }

    public function setup_ajax()
    {
        if (!current_user_can($this->args['capability'])) {
            return;
        }

        add_action('wp_ajax_' . $this->args['action'], [$this, 'wp_hook_ajax_step_1']);
        add_action('wp_ajax_' . $this->args['action'] . '_run', [$this, 'wp_hook_ajax_step_2']);
    }

    public function wp_hook_dashboard_setup()
    {
        if (!current_user_can($this->args['capability'])) {
            return;
        }

        wp_add_dashboard_widget($this->args['id'], $this->args['title'], [$this, 'widget_html']);
    }

    public function widget_html()
    {
        $data = $this->args;
        include $this->args['template'];
    }

    protected function json_encode($array)
    {
        return json_encode($array, JSON_UNESCAPED_UNICODE);
    }

    protected function check_access_user()
    {
        if (!current_user_can($this->args['capability'])) {
            wp_send_json_error(['message' => 'شما حق دسترسی ندارید'], 400);
        }
    }

    public static function import_excel_data($path): array
    {
        // PHPExcel to send
        if (!class_exists('\Shuchkin\SimpleXLSX')) {
            require_once WP_Rahkaran_Loyalty::$plugin_path . '/simplexlsx/src/SimpleXLSX.php';
        }

        if ($xlsx = \Shuchkin\SimpleXLSX::parse($path)) {
            if (empty($xlsx->rows())) {
                return ['status' => false, 'message' => 'محتوای فایل اکسل خالی است'];
            }
            return ['status' => true, 'data' => $xlsx->rows()];
        }

        return [
            'status' => false,
            'message' => \Shuchkin\SimpleXLSX::parseError()
        ];
    }

    public function wp_hook_ajax_step_1()
    {
        if (wp_doing_ajax()) {

            // Check User Capability
            $this->check_access_user();

            // Init Array List
            $array = [];

            // Check Require Excel File
            if ($this->args['require_excel_file'] === true) {

                // Check Require
                if (!isset($_FILES['excel'])) {
                    wp_send_json_error(['message' => 'پارامتر های درخواستی اشتباه می باشد'], 400);
                }

                // Get File Data
                $filename = $_FILES['excel']['name'];
                $filesize = $_FILES['excel']['size'];
                $extension = pathinfo($filename, PATHINFO_EXTENSION);
                $mime = $_FILES["excel"]["type"];
                $tmp_path = $_FILES["excel"]["tmp_name"];

                // Check Mime Type
                $mimes = [
                    'application/vnd.ms-excel',
                    'text/xls',
                    'text/xlsx',
                    'application/vnd.oasis.opendocument.spreadsheet',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                ];
                if (!in_array($mime, $mimes)) {
                    wp_send_json_error(['message' => 'لطفا یک فایل Excel را انتخاب کنید'], 400);
                }

                // Check File Extension
                if (!in_array($extension, ['xls', 'xlsx'])) {
                    wp_send_json_error(['message' => 'لطفا یک فایل Excel را انتخاب کنید'], 400);
                }

                // Check File Size
                $maxSize = $this->args['max_file_size'] * 1024 * 1024;
                if ($filesize > $maxSize) {
                    wp_send_json_error(['message' => 'حجم فایل نباید بیش تر از ' . $this->args['max_file_size'] . ' مگابایت باشد'], 400);
                }

                // Create Directory in Uploads Folder
                $upload = wp_upload_dir(null, false);
                $upload_base_dir = $upload['basedir'];
                $upload_dir = $upload_base_dir . '/excel/';
                if (!is_dir($upload_dir)) {
                    wp_mkdir_p($upload_dir);
                }

                // Upload To Folder
                // @see Standard User https://www.wptricks.com/question/create-new-folder-and-upload-files-to-custom-folder-via-wp_handle_upload/
                // @see https://wordpress.stackexchange.com/questions/141088/wp-handle-upload-how-to-upload-to-a-custom-subdirectory-within-uploads
                $newPath = rtrim($upload_dir, "/") . "/" . "excel-" . get_current_user_id() . "." . $extension;
                $uploadFile = move_uploaded_file($tmp_path, $newPath);
                if (!$uploadFile) {
                    wp_send_json_error(['message' => 'خطا در بارگزاری فایل رخ داده است ، مجدد تلاش کنید'], 400);
                }

                // Import Excel File Data
                $importExcelData = self::import_excel_data($newPath);
                if (!$importExcelData['status']) {
                    wp_send_json_error(['message' => $importExcelData['message']], 400);
                }

                // Validation Excel Import
                $array = $importExcelData['data'];

                // Delete File
                wp_delete_file($newPath);
            }

            // Run Function
            $run = $this->args['get_excel_content']($array, $this->args);

            // Check Has Error
            if (!$run['status'] and !empty($run['message'])) {
                wp_send_json_error(['message' => $run['message']], 400);
            }

            // Show If Empty
            if (empty($run['list'])) {
                wp_send_json_error(['message' => 'لیست خالی می باشد'], 400);
            }

            // Get Number Item
            $number_item = count($run['list']);

            // Save To Option
            update_option($this->args['option_name'], [
                'list' => $run['list'],
                'number_process' => 0
            ], 'no');

            // Setup Message
            $message = '<div data-' . $this->args['id'] . '-step="1">';
            if (isset($run['message']) and !empty($run['message'])) {
                $message .= $run['message'];
            }
            if (isset($run['table']) and is_array($run['table'])) {
                $message .= '<table class="widefat" style="border: 0 !important;">';
                $message .= '<tr>';
                foreach ($this->args['table']['thead'] as $title) {
                    $message .= '<td>' . $title . '</td>';
                }
                $message .= '</tr>';
                foreach ($run['table'] as $title => $val) {
                    $message .= '<tr>';
                    $message .= '<td>' . $title . '</td>';
                    $message .= '<td>' . $val . '</td>';
                    $message .= '</tr>';
                }
                $message .= '</table>';
            }
            $message .= '<p class="submit" style="float: none; margin: 10px -5px;"><input type="submit" name="" id="' . $this->args['id'] . '-button-action" class="button button-primary" value="شروع عملیات"><span class="spinner"></span></p>';
            $message .= '</div>';

            $message .= '<div data-' . $this->args['id'] . '-step="2" style="display:none;">';
            $message .= '<input type="hidden" id="' . $this->args['id'] . '_number_all" value="' . $number_item . '">';
            $message .= '<p>';
            $message .= 'تعداد بروزرسانی:  ';
            $message .= '<span id="' . $this->args['id'] . '_num_page_process">0</span> / ' . $number_item . '</span>';
            $message .= '</p>';
            $message .= '<p><progress id="' . $this->args['id'] . '_html_progress" value="0" max="100" style="height: 40px;width: 100%;"></progress></p>';
            $message .= 'لطفا تا پایان عملیات پنجره مرورگر را نبندید';
            $message .= '</div>';

            $message .= '<div data-' . $this->args['id'] . '-step="3" style="display:none;">';
            $message .= '<p style="text-align: center;background: #fff;padding: 15px;border-radius: 15px;width: 50%;margin: 15px auto;">';
            $message .= 'عملیات با موفقیت انجام شد';
            $message .= '</p>';
            $message .= '</div>';

            // Return
            wp_send_json_success(['message' => $message], 200);
        }
        exit;
    }

    public function wp_hook_ajax_step_2()
    {
        # Create Default Obj
        $return = [
            'process_status' => 'complete',
            'number_process' => 0,
            'percentage' => 0
        ];

        # Check is Ajax WordPress
        if (wp_doing_ajax()) {

            # Check User Capability
            $this->check_access_user();

            # Number Process Per Query
            $number_per_query = $this->args['number_per_process'];

            # Option Name
            $option_name = $this->args['option_name'];

            # Check Number Process
            $i = 0;
            $list_option = get_option($option_name);
            $list = $_saved_list = $list_option['list'];
            $new_number_process = $list_option['number_process'] + $number_per_query;
            $items = [];
            foreach ($list as $key => $item) {
                if ($i > $number_per_query) {
                    break;
                }

                // run Item
                $this->args['process_item']($item, $key);

                // Append To This Process Items
                $items[] = $item;

                // Removed From List
                unset($_saved_list[$key]);

                // Add++
                $i++;
            }

            // After Process Items
            $this->args['after_process_items']($items);

            // Save Option
            $opt = [
                'number_process' => $new_number_process,
                'list' => array_values($_saved_list)
            ];
            update_option($option_name, $opt, 'no');

            # Check End
            if ($_REQUEST['number_all'] > $new_number_process) {
                # Calculate Number Process
                $return['number_process'] = $new_number_process;

                # Calculate Per
                $return['percentage'] = round(($return['number_process'] / $_GET['number_all']) * 100);

                # Set Process
                $return['process_status'] = 'incomplete';

            } else {

                $return['number_process'] = $_REQUEST['number_all'];
                $return['percentage'] = 100;
                delete_option($option_name);

                // After Completed Process
                $this->args['after_completed_process']();
            }

            # Export Data
            wp_send_json($return);
            exit;
        }
    }

}
