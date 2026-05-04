<?php

/*
 *
 *
 *  Convert price list from excel to js
 *
 *
 */

function ManagePricelist()
{
    // Get a brand with a pricelist received

    $error = '';
    $result = QueryExecute( '74_brand_with_pricelist.sql', $error );

    if( $result === false )
    {
        WorkerLog( WORKER_ERROR, "FATAL - 74_brand_with_pricelist.sql: query failed - Error: $error", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }


    // Exit loop if not found

    if( count( $result ) === 0 )
    {
        return false;
    }


    // brand id et alt.

    $brand_id    = $result[0]['id'];
    $brand       = $result[0]['brand'];
    $description = $result[0]['pricelist_description'];
    $uploaded    = $result[0]['pricelist_uploaded'];
    $user_id     = $result[0]['pricelist_uploader'];
    $prev_prd_cnt= $result[0]['pricelist_products_count'];

    $issues = '';


    // Log

    $milliseconds = Milliseconds();
    WorkerLog( WORKER_INFO, "Processing pricelist of brand $brand ($brand_id)...", 0, false, false, 1 );


    // Do the job

    $brand_dir = PathToBrandDirectory( $brand_id );

    if( ! FSDirectoryExists( $brand_dir ) )
    {
        WorkerLog( WORKER_ERROR, "FATAL - Missing directory of brand $brand ($brand_id)", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    $filetype = '';
    $filepath = '';
    $complete = false;

    if( FSFileExists( $brand_dir . 'pricelist.partial.xls' ) )
    {
        $filetype = 'xls';
        $complete = false;
        $filepath = $brand_dir . 'pricelist.partial.xls';
    }
    elseif( FSFileExists( $brand_dir . 'pricelist.partial.xlsx' ) )
    {
        $filetype = 'xlsx';
        $complete = false;
        $filepath = $brand_dir . 'pricelist.partial.xlsx';
    }
    elseif( FSFileExists( $brand_dir . 'pricelist.complete.xls' ) )
    {
        $filetype = 'xls';
        $complete = true;
        $filepath = $brand_dir . 'pricelist.complete.xls';
    }
    elseif( FSFileExists( $brand_dir . 'pricelist.complete.xlsx' ) )
    {
        $filetype = 'xlsx';
        $complete = true;
        $filepath = $brand_dir . 'pricelist.complete.xlsx';
    }
    else
    {
        WorkerLog( WORKER_ERROR, "FATAL - Missing pricelist file of brand $brand ($brand_id)", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    if( $filetype === 'xls' )
    {
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
    }
    else
    {
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
    }

    $reader->setReadDataOnly( true );
    $spreadsheet = $reader->load( $filepath );

    $sheetCount = $spreadsheet->getSheetCount();

    $products = [];

    $missing_column = true;

    for( $sheetIdx = 0; $sheetIdx < $sheetCount; $sheetIdx++ )
    {
        for( $header_y = 1; $header_y < 10; $header_y ++ )
        {
            $codeColumn = 0;
            $prceColumn = 0;
            $dscrColumn = 0;
            $cod2Column = 0;
            $cod3Column = 0;
            $dsc2Column = 0;
            $dsc3Column = 0;
            $dsc4Column = 0;
            $dsc5Column = 0;
            $dateColumn = 0;

            for( $x = 1; $x <= 52; $x++ )
            {
                $value = $spreadsheet->getSheet( $sheetIdx )->getCell( [ $x, $header_y ] )->getValue();
                $value = strtolower( trim( (string)$value ) );

                if( $value == 'codice'       && $codeColumn === 0 ) { $codeColumn = $x; }
                if( $value == 'descrizione'  && $dscrColumn === 0 ) { $dscrColumn = $x; }
                if( $value == 'prezzo'       && $prceColumn === 0 ) { $prceColumn = $x; $currency = "euro"; }
                if( $value == 'prezzo pt'    && $prceColumn === 0 ) { $prceColumn = $x; $currency = "pt.";  }
                if( $value == 'prezzo $'     && $prceColumn === 0 ) { $prceColumn = $x; $currency = "$";    }

                if( $value == 'codice2'      && $cod2Column === 0 ) { $cod2Column = $x; }
                if( $value == 'codice3'      && $cod3Column === 0 ) { $cod3Column = $x; }

                if( $value == 'descrizione2' && $dsc2Column === 0 ) { $dsc2Column = $x; }
                if( $value == 'descrizione3' && $dsc3Column === 0 ) { $dsc3Column = $x; }
                if( $value == 'descrizione4' && $dsc4Column === 0 ) { $dsc4Column = $x; }
                if( $value == 'descrizione5' && $dsc5Column === 0 ) { $dsc5Column = $x; }

                if( $value == 'data'         && $dateColumn === 0 ) { $dateColumn = $x; }
            }

            if( $codeColumn !== 0 && $prceColumn !== 0 && $dscrColumn !== 0 )
            {
                break;
            }
        }

        if( $codeColumn === 0 || $prceColumn === 0 || $dscrColumn === 0 )
        {
            $sheetName = $spreadsheet->getSheet( $sheetIdx )->getTitle();
            $descr2 = str_replace( " ", "-", $description );
            $descr2 = str_replace( "/", "-", $descr2 );
            $descr2 = str_replace( ":", "-", $descr2 );
            WorkerLog( WORKER_WARNING, "Pricelist uploaded on $brand ($brand_id) is missing one or more column(s) on sheet $sheetName; you can downloaload it at https://www.pinaxo.com/badxls/$descr2", 0, true, true, false );
            if( $issues === '' )
            {
                $issues = "Missing column(s) on sheet";
            }
            $issues = "$issues $sheetName";
            if( is_file( PATH_TO_BAD_XLS . "$descr2" ) )
            {
                unlink( PATH_TO_BAD_XLS . "$descr2" );
            }
            copy( $filepath, PATH_TO_BAD_XLS . "$descr2" );
            continue;
        }
        else
        {
            $missing_column = false;
        }

        $blankRows = 0;
        $y = $header_y + 1;

        while( $blankRows < 200 )
        {
            $code = '';
            $cod2 = '';
            $cod3 = '';
            $dscr = '';
            $dsc2 = '';
            $dsc3 = '';
            $dsc4 = '';
            $dsc5 = '';
            $date = '';

            if( true )
            {
                $type = $spreadsheet->getSheet( $sheetIdx )->getCell( [ $codeColumn, $y ] )->getDataType();
                if( $type === \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA )
                {
                    $code = (string)$spreadsheet->getSheet( $sheetIdx )->getCell( [ $codeColumn, $y ] )->getCalculatedValue();
                }
                else
                {
                    $code = (string)$spreadsheet->getSheet( $sheetIdx )->getCell( [ $codeColumn, $y ] )->setDataType( \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING )->getValue();
                }
            }
            if( $cod2Column !== 0 )
            {
                $type = $spreadsheet->getSheet( $sheetIdx )->getCell( [ $cod2Column, $y ] )->getDataType();
                if( $type === \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA )
                {
                    $cod2 = (string)$spreadsheet->getSheet( $sheetIdx )->getCell( [ $cod2Column, $y ] )->getCalculatedValue();
                }
                else
                {
                    $cod2 = (string)$spreadsheet->getSheet( $sheetIdx )->getCell( [ $cod2Column, $y ] )->setDataType( \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING )->getValue();
                }
            }
            if( $cod3Column !== 0 )
            {
                $type = $spreadsheet->getSheet( $sheetIdx )->getCell( [ $cod3Column, $y ] )->getDataType();
                if( $type === \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA )
                {
                    $cod3 = (string)$spreadsheet->getSheet( $sheetIdx )->getCell( [ $cod3Column, $y ] )->getCalculatedValue();
                }
                else
                {
                    $cod3 = (string)$spreadsheet->getSheet( $sheetIdx )->getCell( [ $cod3Column, $y ] )->setDataType( \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING )->getValue();
                }
            }
            $prce = $spreadsheet->getSheet( $sheetIdx )->getCell( [ $prceColumn, $y ] )->getCalculatedValue();
            $dscr = $spreadsheet->getSheet( $sheetIdx )->getCell( [ $dscrColumn, $y ] )->setDataType( \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING )->getValue();
            $dsc2 = $dsc2Column === 0 ? '' : $spreadsheet->getSheet( $sheetIdx )->getCell( [ $dsc2Column, $y ] )->setDataType( \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING )->getValue();
            $dsc3 = $dsc3Column === 0 ? '' : $spreadsheet->getSheet( $sheetIdx )->getCell( [ $dsc3Column, $y ] )->setDataType( \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING )->getValue();
            $dsc4 = $dsc4Column === 0 ? '' : $spreadsheet->getSheet( $sheetIdx )->getCell( [ $dsc4Column, $y ] )->setDataType( \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING )->getValue();
            $dsc5 = $dsc5Column === 0 ? '' : $spreadsheet->getSheet( $sheetIdx )->getCell( [ $dsc5Column, $y ] )->setDataType( \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING )->getValue();
            $date = $dateColumn === 0 ? '' : $spreadsheet->getSheet( $sheetIdx )->getCell( [ $dateColumn, $y ] )->setDataType( \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING )->getValue();

            $code = StringReplace( "$code $cod2 $cod3", "\r", "\n" );
            $code = StringReplace( $code, "\n", " " );
            $code = StringReplace( $code, "  ", " ", STRING_REPEAT );
            $code = trim( $code );

            $dscr = StringReplace( "$dscr $dsc2 $dsc3 $dsc4 $dsc5", "\r", "\n" );
            $dscr = StringReplace( $dscr, "\n", " " );
            $dscr = StringReplace( $dscr, "  ", " ", STRING_REPEAT );
            $dscr = trim( $dscr );

            $y++;

            if( $y % 500 === 0 )
            {
                WorkerAlive();
            }

            if( $code == '' || $prce == '' || $dscr == '' )
            {
                $blankRows++;
                continue;
            }
            else
            {
                $blankRows = 0;
            }

            $products[] = [ 'code' => $code, 'prce' => (float)floatval( $prce ), 'dscr' => $dscr, 'srch' => mb_strtolower( " $code $dscr" ), 'lngh' => 0, 'date' => $date ];
        }
    }

    unset( $reader );
    unset( $spreadsheet );

    if( ! $missing_column && count( $products ) === 0 )
    {
        WorkerLog( WORKER_WARNING, "Pricelist uploaded on $brand ($brand_id) is missing PRODUCTS", 0, true, true, true );
        FSMakeDir( "$brand_dir/uploaded_pricelists" );
        rename( $filepath, "$brand_dir/uploaded_pricelists/$uploaded $description" );
        $result = QueryExecute( '75_brand_with_pricelist_update2.sql', $error, [ 'brand_id' => $brand_id ] );
        if( $result === false )
        {
            WorkerLog( WORKER_ERROR, "FATAL - 75_brand_with_pricelist_update2.sql: query failed - Error: $error", 0, true, true, true );
            WorkerQuitNow();
            /*--- QUIT POINT ---*/
        }
        InvalidateCache( 'brands' );
        $issues = "Missing products";
        $result = QueryExecute( '75_pricelists_per_brand.sql', $error, [ 'brand_id' => $brand_id, 'pricelist' => $description, 'issues' => $issues, 'uploaded' => str_replace( '.', ':', $uploaded ), 'user_id' => $user_id ] );
        if( $result === false )
        {
            WorkerLog( WORKER_ERROR, "FATAL - 75_pricelists_per_brand.sql: query failed - Error: $error", 0, true, true, true );
            WorkerQuitNow();
            /*--- QUIT POINT ---*/
        }
        InvalidateCache( 'pricelists_per_brand' );
        return false;
    }

    if( ! $missing_column )
    {
        if( ! $complete && FSFileExists( $brand_dir . 'pricelist.js' ) )
        {
            // Load the price list

            $text = file_get_contents( $brand_dir . 'pricelist.js' );


            //  Extract the array

            $postabmle = ";\n";
            $text = StringBetween( $text, 'products = ', $postabmle );


            // Turn into assoc array

            $products_prev = json_decode( $text, true );

            if( ! is_array( $products_prev ) )
            {
                WorkerLog( WORKER_ERROR, "FATAL - Cannot json decode previous price list", 0, true, true, true );
                EchoNL( $text );
                WorkerQuitNow();
                /*--- QUIT POINT ---*/
            }

            foreach( $products_prev as &$pp )
            {
                $pp['prce'] = floatval( $pp['prce'] );
            } unset( $pp );

            $y = 0;
            foreach( $products as $p )
            {
                $index = ArrayFind( $products_prev, 'code', $p['code'] );
                if( $index === false )
                {
                    $products_prev[] = $p;
                }
                else
                {
                    $products_prev[$index]['prce'] = $p['prce'];
                    $products_prev[$index]['dscr'] = $p['dscr'];
                    $products_prev[$index]['srch'] = $p['srch'];
                    $products_prev[$index]['lngh'] = $p['lngh'];
                    $products_prev[$index]['date'] = isset( $p['date'] ) ? $p['date'] : '';
                }

                $y++;
                if( $y % 500 === 0 )
                {
                    WorkerAlive();
                }
            }

            $products = $products_prev;
        }

        $products_checked = [];
        foreach( $products as $p )
        {
            if( is_array( $p ) )
            {
                $products_checked[] = $p;
            }
            else
            {
                $vexp = var_export( $p, true );
                WorkerLog( WORKER_WARNING, "Pricelist on $brand ($brand_id): item not array: $vexp", 0, true, false, true );
            }
        }

        $products_count = count( $products_checked );

        $products_json = json_encode( $products_checked, JSON_UNESCAPED_SLASHES );
        $currency_json = json_encode( $currency, JSON_UNESCAPED_SLASHES );

        $products_source = substr( $description, 0, 7 ) === 'idrolab' ? ' DomusPartes © Idrolab' : '';

        $products_js = "\n;\ncurrency = $currency_json;\nproducts = $products_json;\nproducts_source = '$products_source';\n";

        file_put_contents( $brand_dir . 'pricelist.js', $products_js );
        ArrayToFile( $brand_dir . 'pricelist.txt', $products );
    }


    // Archive uploaded file

    FSMakeDir( "$brand_dir/uploaded_pricelists" );
    rename( $filepath, "$brand_dir/uploaded_pricelists/$uploaded $description" );


    // Update the db

    $error = '';

    if( $missing_column )
    {
        $result = QueryExecute( '75_brand_with_pricelist_update.sql', $error, [ 'brand_id' => $brand_id, 'count' => $prev_prd_cnt ] );
    }
    else
    {
        $result = QueryExecute( '75_brand_with_pricelist_update.sql', $error, [ 'brand_id' => $brand_id, 'count' => $products_count ] );
    }



    if( $result === false )
    {
        WorkerLog( WORKER_ERROR, "FATAL - 75_brand_with_pricelist_update.sql: query failed - Error: $error", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    InvalidateCache( 'brands' );


    // Log again

    $milliseconds = Milliseconds( $milliseconds );
    WorkerLog( WORKER_INFO, "Processing pricelist of brand $brand ($brand_id): $milliseconds ms", 0, false, false, 1 );


    // Log into pricelists per brand

    $issues = substr( $issues, 0, 255 );
    $result = QueryExecute( '75_pricelists_per_brand.sql', $error, [ 'brand_id' => $brand_id, 'pricelist' => $description, 'issues' => $issues, 'uploaded' => str_replace( '.', ':', $uploaded ), 'user_id' => $user_id ] );
    if( $result === false )
    {
        WorkerLog( WORKER_ERROR, "FATAL - 75_pricelists_per_brand.sql: query failed - Error: $error", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    InvalidateCache( 'pricelists_per_brand' );


    // Rest

    WorkerAlive();
    sleep(3);


    // Log the db

    WorkerLog( WORKER_INFO, "Processed pricelist of brand $brand ($brand_id)", 0, true, false, false );


    // Return the brand id to sync slave to master

    return $brand_id;
}
