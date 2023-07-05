<?php
/**
 * Miracle Abandoned Carts
 *  Plugin Name: Miracle Abandoned Carts
 *  Plugin URI: https://miracooldigital.com/
 *  Description: plugin made to validate and automate sending abandoned carts to mautic
 *  Version: 1.0
 *  author: Guilherme S
 *  Author URI: https://www.instagram.com/guiapenas/
 *  License: GPLv2 or later
 */

require_once(  __DIR__ . "/abandonedCartCronJob.php");

global $wpdb;

class MiracleAbandonedCarts{
    public function run(){
        $abandonedCartCronJob = new MiracleAbandonedCartsCronJob();
        add_action("miracle_Abandoned_Carts_Cron_Job", array($abandonedCartCronJob, 'abandonedCarts'));
        add_action('admin_menu', array($this ,'Miracle_Admin_Page'));
    }
	public function Miracle_Admin_Page(){
	    add_menu_page('Miracle Mautic Connect', 'Miracle Mautic', 'manage_options', 'Miracle Mautic', array($this ,'MiracleAbandonedCarts_Page_Template'), "", 2);
    }
    public function MiracleAbandonedCarts_Page_Template(){
		$ch = curl_init('http://34.130.104.55:5069/getProfile/szguisantos@gmail.com');

curl_setopt_array($ch, [

    // Equivalente ao -X:
    CURLOPT_CUSTOMREQUEST => 'GET',

    // Equivalente ao -H:

    // Permite obter o resultado
    CURLOPT_RETURNTRANSFER => 1,
]);

$resposta = json_decode(curl_exec($ch), true);
curl_close($ch);
print_r($resposta);
	    echo "<div class='AdminMainContainer'>
            <div class='form'>
                <form>
                    <input class='tokenInput' name='clientKey'>
                    <input class='tokenInput' name='clientSecret'>
                    <input class='tokenInput' name='callBack'>
                </form>
            </div>
        </div>";
}
}

function wp_MiracleAbandonedCarts_init(){
        //instantiate the plugin class
            $MiracleMautic = new MiracleAbandonedCarts();
            //start the execution of the plugin class-base-project
            $MiracleMautic->run();
            
            function miracle_Cron_Schedules($schedules){
    if(!isset($schedules["5 minutes"])){
        $schedules["5 minutes"] = array(
            'interval' => 5*60,
            'display' => __('Once every 5 minutes'));
    }
    if(!isset($schedules["30 minutes"])){
        $schedules["30 minutes"] = array(
            'interval' => 30*60,
            'display' => __('Once every 30 minutes'));
    }
    return $schedules;
}
            add_filter('cron_schedules','miracle_Cron_Schedules');
            
            
            if (! wp_next_scheduled ( 'miracle_Abandoned_Carts_Cron_Job' )) {
                wp_schedule_event(time(), '5 minutes', 'miracle_Abandoned_Carts_Cron_Job');
            }
    }
add_action( 'init', 'wp_MiracleAbandonedCarts_init' );