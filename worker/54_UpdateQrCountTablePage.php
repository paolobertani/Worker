<?php

//
//
// Updates the QR Count Table Blog Page
//
//

function UpdateQrCountTablePage()
{
    // should SKIP ???

    return;


    // should build?

    $status_key = "qr_count_table_page_last_update";
    $today = date( "Y-m-d" );
    $last = StatusGetValue( $status_key );
    if( $last === $today )
    {
        return;
        /*--- EXIT POINT ---*/
    }
    StatusSetValue( $status_key, $today );


    // starting

    $milliseconds = Milliseconds();
    WorkerLog( WORKER_INFO, "Updating QR Count Blog Page Table...", 0, false, false, 1 );


    // Categories from INTL **** an api endpoint should be made for this

    $it = [
        'bathroom'              =>      "Arredo bagno",
        'kitchen'               =>      "Cucina",
        'furniture'             =>      "Arredamento",
        'complements'           =>      "Complementi",
        'tiles'                 =>      "Tiles&Floors",
        'hvac'                  =>      "Termoidraulica",
        'lightning'             =>      "Illuminazione",
        'not_applicable'        =>      "n.a.",
        'private_documents'     =>      "Documenti Privati",
        'design_radiators'      =>      "Termoarredo",
        'users'                 =>      "Utenti",
        'groups'                =>      "Gruppi",
        'electrical'            =>      "Elettrico",
        'building'              =>      "Edilizia"
    ];

    $en = [
        'bathroom'              =>      "Bathroom",
        'kitchen'               =>      "Kitchen",
        'furniture'             =>      "Furniture",
        'complements'           =>      "Furnishing Complements",
        'tiles'                 =>      "Tiles&Floors",
        'hvac'                  =>      "HVAC&Plumbing",
        'lightning'             =>      "Lightning",
        'not_applicable'        =>      "Not Applicable",
        'private_documents'     =>      "Private Documents",
        'design_radiators'      =>      "Design Radiators",
        'users'                 =>      "Users",
        'groups'                =>      "Groups",
        'electrical'            =>      "Electrical",
        'building'              =>      "Construction & Building"
    ];


    // Setup

    $today = new DateTime();
    $date = date_format( $today, "d/m/Y" );

    $html_it =  "<hr />\n".
                "&nbsp;\n".
                "<i>Elenco aggiornato alla data del $date</i>\n".
                "&nbsp;\n".
                "<hr />\n";

    $html_en =  "<hr />\n".
                "&nbsp;\n".
                "<i>List updated as of $date</i>\n".
                "&nbsp;\n".
                "<hr />\n";


    // Query

    $query = 'SELECT ' .
             'documents.qr_count, documents.description, documents.type, documents.`release`, brands.brand, categories.`code` ' .
             'FROM documents,brands,categories ' .
             'WHERE documents.category_id = categories.`id` AND documents.brand_id = brands.`id` and ' .
             'documents.qr_count >= 25 and documents.category_id not in (' . STATS_EXCLUDED_CATEGORIES . ') and documents.expire="UNDEFINED" and documents.pdf=1 and documents.`status`="" ' .
             'ORDER BY categories.position ASC, documents.qr_count DESC';

    $result = QueryExecute( $query, $error );


    // Retrieve max and min qr count

    $qr_max = -1;
    $qr_min = 999999;

    foreach( $result as $record )
    {
        if( $record[ 'qr_count' ] > $qr_max ) $qr_max = $record[ 'qr_count' ];
        if( $record[ 'qr_count' ] < $qr_min ) $qr_min = $record[ 'qr_count' ];
    }

    $qr_dif = $qr_max - $qr_min;
    if( $qr_dif === 0 ) $qr_dif = 999999;


    // Build table

    $last_ctg = null;
    $count = 0;

    $r1 = 238;
    $g1 = 170;
    $b1 = 0;

    $r0 = 0;
    $g0 = 170;
    $b0 = 0;


    foreach( $result as $record )
    {
        $qr_cnt         = $record[ 'qr_count' ];
        $description    = $record[ 'description' ];
        $code           = $record[ 'code' ];
        $type           = $record[ 'type' ];
        $release        = $record[ 'release' ];
        $brand          = $record[ 'brand' ];

        $ctg_it         = $it[ $code ];
        $ctg_en         = $en[ $code ];

        $type_it        = $type === 'L' ? 'Listino' : 'Catalogo';
        $type_en        = $type === 'L' ? 'Pricelist' : 'Catalogue';

        $r = intval( $r0 + ( $r1 - $r0 ) * ( $qr_cnt - $qr_min ) / $qr_dif );
        $g = intval( $g0 + ( $g1 - $g0 ) * ( $qr_cnt - $qr_min ) / $qr_dif );
        $b = intval( $b0 + ( $b1 - $b0 ) * ( $qr_cnt - $qr_min ) / $qr_dif );

        $description    = htmlspecialchars( $description );
        $brand          = htmlspecialchars( $brand );

        if( $code !== $last_ctg )
        {
            if( $last_ctg !== null )
            {
                $html_it .= "</table>\n";
                $html_en .= "</table>\n";
            }

            $last_ctg = $code;
            $html_it .= "&nbsp;\n<strong>$ctg_it</strong>\n<table>\n";
            $html_en .= "&nbsp;\n<strong>$ctg_en</strong>\n<table>\n";
        }

        $html_it .="<tr><td style='width: 10%;'>$type_it</td><td style='width: 70%;'>$brand: $description</td><td style='width: 10%; text-align: center;'><span style='background: #777; color: #fff; border-radius: 3px;'>&nbsp;$release&nbsp;</span></td><td style='width: 10%; text-align: right;'> <strong><span style='color: rgb($r,$g,$b);'>$qr_cnt</span></strong> QR</td></tr>\n";
        $html_en .="<tr><td style='width: 10%;'>$type_en</td><td style='width: 70%;'>$brand: $description</td><td style='width: 10%; text-align: center;'><span style='background: #777; color: #fff; border-radius: 3px;'>&nbsp;$release&nbsp;</span></td><td style='width: 10%; text-align: right;'> <strong><span style='color: rgb($r,$g,$b);'>$qr_cnt</span></strong> QR</td></tr>\n";

        $count++;
    }

    $html_it .= "</table>\n";
    $html_en .= "</table>\n";


    // Post

    $id = 459;
    $user = "????";
    $pass = "????";
    $auth = base64_encode( "$user:$pass" );
    $data = "id=$id&content=" . urlencode( $html_it );
    $result = Curl( "https://www.pinaxo.com/blog/it/wp-json/wp/v2/pages/$id", $data, 'Authorization: Basic ' .$auth );
    $status = intval( $result['status'] );
    if( $status >= 300 )
    {
        CurlDeleteCookiesFile();
        WorkerLog( WORKER_ERROR, "FATAL - UpdateQrCountTablePage: posting IT page failed with status $status", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    $id = 430;
    $data = "id=$id&content=" . urlencode( $html_en );
    $user = "????";
    $pass = "????";
    $auth = base64_encode( "$user:$pass" );
    $result = Curl( "https://www.pinaxo.com/blog/en/wp-json/wp/v2/pages/$id", $data, 'Authorization: Basic ' . $auth );
    $status = intval( $result['status'] );
    if( $status >= 300 )
    {
        CurlDeleteCookiesFile();
        WorkerLog( WORKER_ERROR, "FATAL - UpdateQrCountTablePage: posting EN page failed with status $status", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    CurlDeleteCookiesFile();


    // Log

    $milliseconds = Milliseconds( $milliseconds );
    WorkerLog( WORKER_INFO, "Updated QR Count Blog Page Table: $count rows; $milliseconds ms", 0, true, false, 1 );
    sleep(3);
}
