<?php
/*
 * Plugin Name: Cf7Save Extension
 * Description: This Plugins Saves Contact form 7 Form submission into Database so you can Preview & Export. You must install contact form 7 in order to use this plugin
 * Author: P3JX
 * License: GPL2
 * Version: 1
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * */

class pxsavecf7 {

    function __construct() {
        add_action('wpcf7_submit', array($this, 'action_wpcf7_submit'), 10, 2);

        add_action('admin_menu', array($this, 'px_register_admin_menu'));

        add_action('admin_head', array($this, 'px_backend_styles'));

        add_action('admin_enqueue_scripts', array($this, 'px_backend_styles'));

        add_action('admin_init', array($this, 'child_plugin_has_parent_plugin'));
    }

    function px_register_admin_menu() {
        add_submenu_page('wpcf7', 'Submitted Forms', 'Submitted Forms', 'manage_options', basename(__FILE__), array($this, 'px_options_page'));
    }

    function child_plugin_has_parent_plugin() {
        if (is_admin() && current_user_can('activate_plugins') && !is_plugin_active('contact-form-7/wp-contact-form-7.php')) {
            add_action('admin_notices', array($this, 'child_plugin_notice'));

            deactivate_plugins(plugin_basename(__FILE__));

            if (isset($_GET['activate'])) {
                unset($_GET['activate']);
            }
        }
    }

    function child_plugin_notice() {
        ?><div class="error"><p>This plugin requires the Contact form 7 plugin to be installed and active.</p></div><?php
    }

    function px_backend_styles() {

        wp_enqueue_style('datatables',plugin_dir_url(__FILE__).'/libs/datatables.min.css');
        wp_enqueue_style('cf7save_styles',plugin_dir_url(__FILE__).'/libs/cf7styles.css');

        wp_enqueue_script('cf7save_script',plugin_dir_url(__FILE__).'/libs/cf7scripts.js');
        wp_enqueue_script('datatables',plugin_dir_url(__FILE__).'/libs/datatables.min.js');

    }

    function action_wpcf7_submit($instance, $result) {

        if($result['status'] === 'validation_failed'){
            return;
        }

        global $wpdb;

        $post = $_POST;

        $id = ($instance->id);
        $title = ($instance->title);
        $subject = ($instance->mail['subject']);
        $recipient = ($instance->mail['recipient']);
        $email = $instance->mail['body'];
        $email_2 = $instance->mail_2['body'];

        $subject_2 = '';
        $recipient_2 = '';

        if ($instance->mail_2['active']) {
            $subject_2 = ($instance->mail_2['subject']);
            $recipient_2 = ($instance->mail_2['recipient']);
        }

        $max_submit_id = $wpdb->get_row('SELECT MAX(form_submit_id) FROM ' . $wpdb->prefix . 'cf7save', ARRAY_A);

        $max_submit_id = $max_submit_id['MAX(form_submit_id)'] + 1;

        /* debugging stuff */
//        $myfile = fopen("newfile.txt", "w");
//
//        ob_start();
//
//        var_dump($result);
//
//        fwrite($myfile, ob_get_clean());
//
//        fclose($myfile);
        /* end debugging stuff */


        foreach ($post as $key => $p) {

            if (strpos($key, '_') === 0) {
                continue;
            }

            $insert_array = array('cf7_id' => $id, 'cf7_title' => $title, 'cf7_subject' => $subject, 'cf7_recipient' => $recipient, 'cf7_subject_2' => $subject_2, 'cf7_recipient_2' => $recipient_2, 'key' => $key, 'value' => $p, 'mail' => $email, 'mail_2' => $email_2, 'form_submit_id' => $max_submit_id);

            $wpdb->insert($wpdb->prefix . 'cf7save', $insert_array);
        }
    }

    function px_options_page() {

        global $wpdb;

        $query = 'SELECT DISTINCT cf7_id, cf7_title FROM ' . $wpdb->prefix . 'cf7save';

        $result = $wpdb->get_results($query, OBJECT_K);
        ?>
        <div class="wrap">

            <h1>Save & Export</h1>
            <h3>Select contact form</h3>

            <form method="get" action="">

                <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>">

                <select name="pxcf7_form_id" title="Select Form" class="input-full">
                    <?php foreach ($result as $row): ?>
                        <option value="<?php echo $row->cf7_id ?>"><?php echo $row->cf7_title ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="submit" class="button button-primary button-large" value="View Form">
            </form>

            <!-- when post [show table] -->

            <?php
            if (isset($_GET['pxcf7_form_id'])) {

                $form_id = $_GET['pxcf7_form_id'];

                $query = 'SELECT *  FROM ' . $wpdb->prefix . 'cf7save WHERE cf7_id="' . $form_id . '" GROUP BY form_submit_id';

                $result = $wpdb->get_results($query);
                ?>

                <div class="container">

                    <form method="get">
                        <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>">
                        <input type="hidden" name="pxcf7_form_id" value="<?php echo $_GET['pxcf7_form_id'] ?>">
                        <input type="hidden" value="true" name="export_csv">
                        <button type="submit" class="button button-default button-large export_to_csv" style="float: right;margin-bottom: 10px;">Export to CSV</button>
                    </form>

                    <?php
                    if (isset($_GET['export_csv'])) {

                        foreach ($result as $one_result) {

                            $query = 'SELECT `key`, value FROM ' . $wpdb->prefix . 'cf7save WHERE form_submit_id="' . $one_result->form_submit_id . '"';
                            $data = $wpdb->get_results($query);

                            foreach ($data as $item) {
                                $export_result = $item->key . ',' . $item->value;
                            }
                        }
                    }
                    ?>

                    <table id="grid-basic" class="table table-hover table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Submit Date</th>
                                <th>Data</th>
                                <th>View Full Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($result as $key => $value): ?>
                                <tr>
                                    <td><?php echo $key ?></td>
                                    <td><?php echo $value->date ?></td>
                                    <td>
                                        <?php $query = 'SELECT `key`, value FROM ' . $wpdb->prefix . 'cf7save WHERE form_submit_id="' . $value->form_submit_id . '"'; ?>
                                        <?php
                                        $data = $wpdb->get_results($query);
                                        $x = 0;
                                        foreach ($data as $item):
                                            $x++;
                                            if ($x > 4) {
                                                break;
                                            }
                                            ?>
                                        
                                            <strong>[</strong> <span style="color: red"> <?php echo $item->key ?></span> |
                                            <span style="color: green">
                                                <?php
                                                if (strlen($item->value > 20)) {
                                                    echo substr($item->value, 0, 15) . '...';
                                                } else {
                                                    echo $item->value;
                                                }
                                                ?>
                                            </span>
                                            <strong>]</strong>
                                        <?php endforeach; ?>
                                    </td>
                                    <td>
                                        <form method="get" action="">
                                            <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>">

                                            <input type="hidden" name="cf7_id" value="<?php echo $form_id ?>">
                                            <input type="hidden" name="form_submit_id" value="<?php echo $value->form_submit_id ?>">
                                            <button type="submit" class="button button-primary">View</button>
                                        </form>

                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php
            }
            ?>

            <!-- EXECUTE BELOW WHEN POST -->

            <?php
            if (isset($_GET['cf7_id']) && $_GET['form_submit_id']) {

                $result = $wpdb->get_results('SELECT * FROM ' . $wpdb->prefix . 'cf7save WHERE cf7_id = "' . $_GET['cf7_id'] . '" AND form_submit_id = "' . $_GET['form_submit_id'] . '"');
                ?>
                <br>
                <div class="cf7_item_wrapper">
                    <h2>User Inputs</h2>
                    <?php
                    foreach ($result as $item) {
                        ?>
                        <div class="row">
                            <label><?php echo $item->key ?></label>
                            <input type="text" readonly value="<?php echo $item->value ?>">
                        </div>
                        <?php
                    }
                    ?>
                    <h2>Mail Settings</h2>
                    <?php
                    foreach ($result as $key => $row) :
                        ?>
                        <div class="row">
                            <label>ID</label>
                            <input type="text" readonly value="<?php echo $row->id ?>">
                        </div>
                        <div class="row">
                            <label>Form Title</label>
                            <input type="text" readonly value="<?php echo $row->cf7_title ?>">
                        </div>
                        <div class="row">
                            <label>Contact form 7 ID</label>
                            <input type="text" readonly value="<?php echo $row->cf7_id ?>">
                        </div>
                        <div class="row">
                            <label>Mail 1</label>
                            <textarea type="text" readonly><?php echo $row->mail ?></textarea>
                        </div>
                        <div class="row">
                            <label>Mail 2</label>
                            <textarea type="text" readonly><?php echo $row->mail_2 ?></textarea>
                        </div>

                        <div class="row">
                            <label>Email Subject</label>
                            <input type="text" readonly value="<?php echo $row->cf7_subject ?>">
                        </div>
                        <div class="row">
                            <label>Email Subject 2</label>
                            <input type="text" readonly value="<?php echo $row->cf7_subject_2 ?>">
                        </div>
                    </div>
                    <?php
                    //just want to run it once
                    break;
                    
                endforeach;
            }
            ?>
            <!--     close div wrap-->
        </div>
        <?php
    }

}

new pxsavecf7();

register_activation_hook(__FILE__, 'pxcf7_create_table');

function pxcf7_create_table() {

    global $wpdb;
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    //customers
    $table_name = $wpdb->prefix . 'cf7save';
    if ($wpdb->get_var('SHOW TABLES LIKE "' . $table_name . '"') != $table_name) {
        $sql = 'CREATE TABLE ' . $table_name . '(
			id INTEGER UNSIGNED AUTO_INCREMENT,
			cf7_id INTEGER,
			form_submit_id INTEGER,
			mail VARCHAR(255),
			mail_2 VARCHAR(255),
			cf7_title VARCHAR(255),
			cf7_subject VARCHAR(255),
			cf7_subject_2 VARCHAR(255),
			cf7_recipient VARCHAR(255),
			cf7_recipient_2 VARCHAR(255),
			`key` VARCHAR(255),
			value VARCHAR(255),
			date DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id) )';

        dbDelta($sql);
    }
}
