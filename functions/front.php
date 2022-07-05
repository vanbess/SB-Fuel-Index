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
        update_option('fuel-sa-next-run', $time_now + $next_run);

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
        update_option('fuel-sa-next-run', $time_now + $next_run);

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

        <!-- indicator container -->
        <div id="fpi-container">

            <!-- title box -->
            <div class="fpi-data-box">
                <table id="fpi-table-first">
                    <tr>
                        <td id="fpi-title-first">FUEL PRICE INDICATOR</td>
                    </tr>
                    <tr>
                        <td id="fpi-need-fuel"><a href="<?php echo get_bloginfo('url') . '/contact/'; ?>">Need Fuel?</a></td>
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
                            <th>50 PPM</th>
                            <th>500 PPM</th>
                        </tr>
                        <tr>
                            <th>Reef</th>
                            <?php foreach ($diesel_reef as $index => $data) : ?>
                                <td>R<?php echo $data['cost']; ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <th>Coast</th>
                            <?php foreach ($diesel_coast as $index => $data) : ?>
                                <td>R<?php echo $data['cost']; ?></td>
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
                    <table>
                        <tr>
                            <th>Type</th>
                            <th>ULP 93</th>
                            <th>ULP 95</th>
                        </tr>
                        <tr>
                            <th>Reef</th>
                            <?php foreach ($petrol_reef as $index => $data) : ?>
                                <td>R<?php echo $data['cost']; ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <th>Coast</th>
                            <?php foreach ($petrol_coast as $index => $data) : ?>
                                <td>R<?php echo $data['cost']; ?></td>
                            <?php endforeach; ?>
                        </tr>
                    </table>
                </div>
            </div>

        </div>

        <style>
            div#fpi-container {
                position: absolute;
                background: #ffffffeb;
                height: auto;
                width: 800px;
                top: 43px;
                right: 20vw;
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
                padding: 0 10px;
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

            /* 1440px */
            @media screen and (max-width: 1440px) {
                div#fpi-container {
                    right: 10vw;
                }
            }

            /* 1366px */
            @media screen and (max-width: 1440px) {
                div#fpi-container {
                    right: 8vw;
                }
            }

            /* 1280px */
            @media screen and (max-width: 1280px) {
                div#fpi-container {
                    right: 7vw;
                }
            }

            /* 800px */
            @media screen and (max-width: 800px) {
                div#fpi-container {
                    right: 0vw;
                    top: 9vh;
                    z-index: 100;
                    border-radius: 0px;
                }
            }

            /* 768px */
            @media screen and (max-width: 768px) {
                div#fpi-container {
                    top: 11.2vh;
                }
            }

            /* 414px */
            @media screen and (max-width: 414px) {
                div#fpi-container {
                    display: none;
                }
            }
        </style>

<?php

        $output = ob_get_clean();

        return $output .= $top_header;

    // if some issue with $curr_data, just return $top_header
    else :

        return $top_header;

    endif;

});
