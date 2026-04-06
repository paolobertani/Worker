<?php

/*
 *
 *
 *
 *  Updates Dates Generator
 *
 *
 *
 */



define( 'ROOT',                     __DIR__ );  // Path to script's directory without trailing slash


/*
 *
 *  INCLUDE
 *
 */

// require_once ROOT . '/include/strings.php';
// require_once ROOT . '/include/milliseconds.php';
// require_once ROOT . '/include/echo.php';
// require_once ROOT . '/include/query.php';
// require_once ROOT . '/include/arrays.php';
// require_once ROOT . '/include/arguments.php';
// require_once ROOT . '/include/fs.php';
// require_once ROOT . '/include/mailer.php';
// require_once ROOT . '/include/exec.php';

$year = 2022;
$month = 11;
$day = 1;

$qty = 2400;

$whenDate = new DateTime();

echo "from\twhen\taction\tday\n";

for( $i = 0; $i < $qty; $i++ )
{
    // `from` value

    $fromDay = $day;
    $fromMonth = $month - 1;
    $fromYear = $year;

    if( $fromMonth === 0 )
    {
        $fromMonth = 12;
        $fromYear--;
    }

    $from = $fromYear . '-' . str_pad( (string)$fromMonth, 2, "0", STR_PAD_LEFT) . '-' . str_pad( (string)$fromDay, 2, "0", STR_PAD_LEFT);


    // `when` value

    $whenDay = $day;
    $whenMonth = $month;
    $whenYear = $year;

    $when = $whenYear . '-' . str_pad( (string)$whenMonth, 2, "0", STR_PAD_LEFT) . '-' . str_pad( (string)$whenDay, 2, "0", STR_PAD_LEFT);

    $whenDate->setDate( $whenYear, $whenMonth, $whenDay );
    $dayOfWeek = intval( $whenDate->format( 'N' ) );

    if( $dayOfWeek === 1 )
    {
        $whenDate->add( new DateInterval( 'P1D' ) );
    }
    elseif( $dayOfWeek === 7 )
    {
        $whenDate->add( new DateInterval( 'P2D' ) );
    }
    elseif( $dayOfWeek === 6 )
    {
        $whenDate->sub( new DateInterval( 'P2D' ) );
    }
    elseif( $dayOfWeek === 5 )
    {
        $whenDate->sub( new DateInterval( 'P1D' ) );
    }




    // output

    $whenDate->sub( new DateInterval( 'P1D' ) );
    $when = $whenDate->format( 'Y-m-d' );
    $day_name = strtolower( $whenDate->format( 'l' ) );
    $action = 'notice';
    echo "$from\t$when\t$action\t$day_name\n";

    $whenDate->add( new DateInterval( 'P1D' ) );
    $when = $whenDate->format( 'Y-m-d' );
    $day_name = strtolower( $whenDate->format( 'l' ) );
    $action = 'snd0';
    echo "$from\t$when\t$action\t$day_name\n";

    $whenDate->add( new DateInterval( 'P1D' ) );
    $when = $whenDate->format( 'Y-m-d' );
    $day_name = strtolower( $whenDate->format( 'l' ) );
    $action = 'snd1';
    echo "$from\t$when\t$action\t$day_name\n";



    // move forward

    if( $day === 1 )
    {
        $day = 15;
    }
    elseif( $day === 15 )
    {
        $day = 1;
        $month++;

        if( $month > 12 )
        {
            $month = 1;
            $year++;
        }
    }
    else
    {
        echo "error\n";
        exit(0);
    }
}





