<?php

/**
 * Admin page
 */
add_action('admin_menu', function () {
    add_menu_page('Fuel Price Index Settings', 'Fuel Price Index', 'manage_options', 'sb-fpi', 'sb_fpi', 'dashicons-lightbulb');
});

function sb_fpi()
{

    global $title;
?>

    <div id="sb-fpi-cont">
        <h2><?php echo $title; ?></h2>

        <?php
        // save API key
        if (isset($_POST['save-fpi-settings'])) :
            update_option('fuel-sa-api-key', $_POST['fuel-sa-api-key'], false);
            update_option('fuel-sa-update-frequency', $_POST['fuel-sa-update-frequency'], false);
        ?>
            <div class="notice notice-success is-dismissible" style="margin-left: 0;">
                <p><?php _e('API key successfully saved.'); ?></p>
            </div>
        <?php
        endif;
        ?>

        <p>
            <b>
                <i>
                    <u>TAKE NOTE: </u>In order for the fuel price index to display on the front-end, you will need to sign up for an API key on <a href="https://www.fuelsa.co.za/#api" target="_blank">Fuel SA</a>. If no valid API key is present, or the field below is left empty, the fuel price index will not display on the front-end of the site.
                </i>
            </b>
        </p>

        <form action="" method="POST">

            <!-- api key -->
            <p>
                <b>
                    <i><label for="fuel-sa-api-key">Enter your Fuel SA API key below and hit save:</label></i>
                </b>
            </p>

            <p><input type="text" name="fuel-sa-api-key" id="fuel-sa-api-key" style="width: 450px" value="<?php echo get_option('fuel-sa-api-key'); ?>"></p>

            <!-- update frequency -->
            <p>
                <b>
                    <i><label for="fuel-sa-update-frequency">How many times to do you want fuel prices to update per day?</label></i>
                </b>
            </p>

            <p>
                <select name="fuel-sa-update-frequency" id="fuel-sa-update-frequency" data-opt="<?php echo get_option('fuel-sa-update-frequency'); ?>">
                    <option value="5">5 times</option>
                    <option value="10">10 times</option>
                    <option value="15">15 times</option>
                    <option value="20">20 times</option>
                    <option value="25">25 times</option>
                    <option value="30">30 times</option>
                </select><br>

                <span class="fi-info" style="font-size: 12px;"><i><b>Default value is 5 times per day. Select a higher value (up to 30 times per day) if you would like to increase the frequency of updates.</b></i></span>
            </p>

            <!-- submit -->
            <p>
                <input type="submit" value="Save FPI Settings" name="save-fpi-settings" class="button button-primary button-large">
            </p>

        </form>

    </div>

    <script>
        $ = jQuery;

        $(document).ready(function() {
            $('#fuel-sa-update-frequency').val($('#fuel-sa-update-frequency').data('opt'));
        });
    </script>

    <style>
        div#sb-fpi-cont>h2 {
            background: white;
            padding: 15px 20px;
            margin-top: 0;
            margin-left: -20px;
            box-shadow: 0px 2px 4px lightgrey;
        }
    </style>

<?php }
