<?php

/**
 * Render front
 */

add_filter('et_html_top_header', function ($top_header) {

    // retrieve settings
    $index_ud_freq = get_option('fuel-sa-update-frequency');
    $index_api_key = get_option('fuel-sa-api-key');

    // bail early if issues with API key
    if (!$index_api_key || $index_api_key == '') :
        return $top_header;
    endif;

    // work out retrieval rest period
    $rest_period = 86400 / $index_ud_freq;

    // get current time in seconds
    $time_now = time();

    // retrieve calculated next run time from db
    $next_run = get_option('fuel-sa-next-run');

    // if $next_run exists and current time is later than or equal to next run time, run retrieval again
    if ($next_run && $time_now >= $next_run) :

        // curl request to retrieve pricing data
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL            => 'https://api.fuelsa.co.za/exapi/fuel/current',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'GET',
            CURLOPT_HTTPHEADER     => array(
                "key: $index_api_key"
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);

        $decoded = json_decode($response, true);

        // save response to database
        update_option('fuel-sa-curr-data', maybe_serialize($decoded));

        // calc next run
        $next_run = $time_now + $rest_period;

        // save run time to database
        update_option('fuel-sa-next-run', $next_run);

        // log runs so we can keep track of requests
        file_put_contents(FI_PATH . 'log/request.log', PHP_EOL . 'Last run time: ' . date('j F Y h:i:s', $time_now) . '. Next run: ' . date('j F Y h:i:s', $next_run), FILE_APPEND);

    // if $next_run time does not yet exist, run initial retrieval and create/save next run time in the process
    elseif (!$next_run) :

        // curl request to retrieve pricing data
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL            => 'https://api.fuelsa.co.za/exapi/fuel/current',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'GET',
            CURLOPT_HTTPHEADER     => array(
                "key: $index_api_key"
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);

        $decoded = json_decode($response, true);

        // save response to database
        update_option('fuel-sa-curr-data', maybe_serialize($decoded));

        // calc next run
        $next_run = $time_now + $rest_period;

        // save run time to database
        update_option('fuel-sa-next-run', $next_run);

        // log runs so we can keep track of requests
        file_put_contents(FI_PATH . 'log/request.log', PHP_EOL . 'Last run time: ' . date('j F Y h:i:s', $time_now) . '. Next run: ' . date('j F Y h:i:s', $next_run), FILE_APPEND);

    endif;

    // retrieve curr data
    $curr_data = maybe_unserialize(get_option('fuel-sa-curr-data'));

    // check if $curr_data is present, not empty and array before doing anything else
    if ($curr_data && is_array($curr_data) && !empty($curr_data)) :


        // setup raw petrol and diesel data
        $petrol_data_raw = $curr_data['petrol'];
        $diesel_data_raw = $curr_data['diesel'];

        // setup reef and coast data arrays
        $petrol_reef  = [];
        $petrol_coast = [];
        $diesel_reef  = [];
        $diesel_coast = [];

        // sort petrol
        foreach ($petrol_data_raw as $index => $data) :

            // skip LRP
            if ($data['type'] === 'LRP') :
                continue;
            endif;

            // reef data
            if ($data['location'] === 'Reef') :
                $petrol_reef[] = [
                    'octane' => $data['octane'],
                    'cost'   => $data['value'] / 100
                ];
            endif;

            // coast data
            if ($data['location'] === 'Coast') :
                $petrol_coast[] = [
                    'octane' => $data['octane'],
                    'cost'   => $data['value'] / 100
                ];
            endif;

        endforeach;

        // sort diesel
        foreach ($diesel_data_raw as $index => $data) :

            // reef data
            if ($data['location'] === 'Reef') :
                $diesel_reef[] = [
                    'ppm'  => $data['ppm'],
                    'cost' => $data['value'] / 100
                ];
            endif;

            // coast data
            if ($data['location'] === 'Coast') :
                $diesel_coast[] = [
                    'ppm'  => $data['ppm'],
                    'cost' => $data['value'] / 100
                ];
            endif;

        endforeach;

        ob_start(); ?>

        <!-- fpi display button mobile -->
        <button type="button" id="fpi-disp-mob">FUEL PRICE INDICATOR</button>

        <!-- fpi overlay -->
        <div id="fpi-overlay" style="display: none;"></div>

        <!-- indicator container -->
        <div id="fpi-container">

            <!-- hide button for mobile -->
            <button type="button" id="fpi-hide-mob" title="close" style="display: none;">x</button>

            <!-- title box -->
            <div class="fpi-data-box">
                <table id="fpi-table-first">
                    <tr>
                        <td id="fpi-title-first">FUEL PRICE INDICATOR</td>
                    </tr>
                    <tr>
                        <td id="fpi-need-fuel"><a href="<?php echo get_bloginfo('url') . '/contact/'; ?>">Order Now</a></td>
                    </tr>
                </table>
            </div>

            <!-- diesel data -->
            <div class="fpi-data-box">

                <!-- vertical title -->
                <div class="fpi-section-title">
                    <table>
                        <tr>
                            <th class="fpi-title">
                                Diesel
                            </th>
                        </tr>
                    </table>
                </div>

                <!-- data table -->
                <div class="fpi-section-data">
                    <table>
                        <tr>
                            <th>Type</th>
                            <!-- <th>500 PPM</th> -->
                            <th>50 PPM</th>
                        </tr>
                        <tr>
                            <th>Inland</th>
                            <?php foreach ($diesel_reef as $index => $data) : if ($index === 0) : continue;
                                endif; ?>
                                <td>R<?php echo number_format($data['cost'], 2, '.', ''); ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <th>Coast</th>
                            <?php foreach ($diesel_coast as $index => $data) : if ($index === 0) : continue;
                                endif; ?>
                                <td>R<?php echo number_format($data['cost'], 2, '.', ''); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- petrol data -->
            <div class="fpi-data-box">

                <!-- vertical title -->
                <div class="fpi-section-title">
                    <table>
                        <tr>
                            <th class="fpi-title">
                                Petrol
                            </th>
                        </tr>
                    </table>
                </div>

                <!-- data table -->
                <div class="fpi-section-data">

                    <?php
                    // reverse petrol array so they display in correct order (low to high)
                    $petrol_coast = array_reverse($petrol_coast);
                    $petrol_reef = array_reverse($petrol_reef);

                    ?>

                    <table>
                        <tr>
                            <th>Type</th>
                            <th>ULP 93</th>
                            <th>ULP 95</th>
                        </tr>
                        <tr>
                            <th>Inland</th>
                            <?php foreach ($petrol_reef as $index => $data) : ?>
                                <td>R<?php echo number_format($data['cost'], 2, '.', ''); ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <th>Coast</th>
                            <?php foreach ($petrol_coast as $index => $data) : ?>
                                <td>R<?php echo number_format($data['cost'], 2, '.', ''); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    </table>
                </div>
            </div>

        </div>

        <style>
            button#fpi-disp-mob {
                display: none;
                position: fixed;
                right: -84px;
                top: 27vh;
                z-index: 1000;
                transform: rotate(90deg);
                background: #cba35a;
                border: none;
                font-size: 16px;
                padding: 10px 15px;
                color: black;
                font-weight: 600;
            }

            button#fpi-hide-mob {
                display: none;
            }

            div#fpi-container {
                position: absolute;
                background: #ffffffeb;
                height: auto;
                width: 800px;
                top: 41px;
                right: 1.5vw;
                z-index: 1000000;
                padding: 0 15px;
                border-radius: 2px;
                display: inline-flex;
                font-size: 13px;
            }

            .fpi-data-box {
                width: 33%;
                display: inline-flex;
            }

            th.fpi-title {
                writing-mode: vertical-rl;
                text-orientation: mixed;
            }

            .fpi-section-title {
                background: black;
                color: #cba35a;
                padding: 20px 10px;
            }

            .fpi-section-data th,
            .fpi-section-data td {
                padding: 0 9px;
            }

            table#fpi-table-first {
                text-align: center;
            }

            td#fpi-title-first {
                padding: 0 40px;
                font-size: 14px;
                font-weight: 700;
            }

            td#fpi-need-fuel {
                font-weight: 600;
                letter-spacing: 0.5px;
                font-size: 13px;
            }

            .hide-fpi-button {
                display: none !important;
            }

            /* 1536px */
            @media screen and (max-width: 1536px) {}

            /* 1440px */
            @media screen and (max-width: 1440px) {
                div#fpi-container {
                    right: 2vw;
                }
            }

            /* 1366px */
            @media screen and (max-width: 1366px) {}

            /* 1280px */
            @media screen and (max-width: 1280px) {}

            /* 962px */
            @media screen and (max-width: 962px) {

                div#fpi-overlay {
                    width: 100vw;
                    height: 100vh;
                    position: fixed;
                    z-index: 100;
                    background: #000000ba;
                }

                div#fpi-container {
                    width: 90%;
                    position: fixed;
                    display: none;
                    left: 22px;
                    top: 140px;
                    padding: 20px;
                    border-radius: 3px;
                    z-index: 100001;
                }

                button#fpi-hide-mob {
                    display: block !important;
                    width: 25px;
                    height: 25px;
                    border-radius: 50%;
                    background: #cba35a;
                    position: absolute;
                    right: -15px;
                    top: -15px;
                    border: none;
                    line-height: 1.6;
                    z-index: 12000;
                }

                div#fpi-container {
                    right: 0vw;
                    top: 22vh;
                    z-index: 100;
                    border-radius: 0px;
                }

                button#fpi-disp-mob {
                    display: block;
                    transition: all 0.3s ease-in-out;
                    top: 56vh;
                }

                .fpi-section-title {
                    padding: 31px 10px;
                }

                table#fpi-table-first {
                    position: relative;
                    bottom: 10px;
                }

                td#fpi-title-first {
                    padding: 0 25px 0px;
                }
            }

            /* 800px */
            @media screen and (max-width: 800px) {
                button#fpi-disp-mob {
                    top: 24vh;
                }

                div#fpi-container {
                    top: 11vh;
                }
            }

            /* 768px */
            @media screen and (max-width: 768px) {
                button#fpi-disp-mob {
                    top: 33vh;
                }

                div#fpi-container {
                    top: 13vh;
                    width: 93%;
                }
            }

            /* 601px */
            @media screen and (max-width: 601px) {
                button#fpi-disp-mob {
                    top: 29vh;
                }

                button#fpi-hide-mob {
                    right: 15px;
                    top: 15px;
                }

                .fpi-section-title>table {
                    width: 100%;
                }

                .fpi-data-box {
                    width: 100%;
                    display: block;
                    text-align: center;
                    margin-bottom: 20px;
                }

                .fpi-section-data>table {
                    width: 100%;
                }

                .fpi-section-title {
                    padding: 8px 8px;
                    text-align: center;
                    margin-bottom: 15px;
                }

                table#fpi-table-first {
                    width: 100%;
                    top: 0px;
                }

                div#fpi-container {
                    top: 14vh;
                    width: 92%;
                }

                th.fpi-title {
                    writing-mode: horizontal-tb;
                }
            }

            /* 414px */
            @media screen and (max-width: 414px) {
                button#fpi-disp-mob {
                    top: 34vh;
                }

                div#fpi-container {
                    top: 16vh;
                    width: 89%;
                }
            }

            /* 375px */
            @media screen and (max-width: 375px) {
                button#fpi-disp-mob {
                    top: 35vh;
                }
            }

            /* 360px */
            @media screen and (max-width: 360px) {
                button#fpi-disp-mob {
                    top: 43vh;
                }

                div#fpi-container {
                    top: 21vh;
                    width: 88%;
                }
            }
        </style>

        <script id="show-hide-fpi-mob">
            jQuery(document).ready(function($) {

                // show fpi
                $('button#fpi-disp-mob').click(function(e) {
                    e.preventDefault();
                    $('div#fpi-overlay').show();
                    $('div#fpi-container').slideDown();
                    $(this).addClass('hide-fpi-button');
                });

                // hide fpi
                $('button#fpi-hide-mob').click(function(e) {
                    e.preventDefault();
                    $(this).parent().slideUp();
                    $('div#fpi-overlay').hide();
                    $('button#fpi-disp-mob').removeClass('hide-fpi-button');
                });

            });
        </script>

<?php

        $output = ob_get_clean();

        return $output .= $top_header;

    // if some issue with $curr_data, just return $top_header
    else :

        return $top_header;

    endif;
});
