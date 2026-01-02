<?php

$s_arr = array(
    'login' => 'site/login-form',
    'glowna' => 'site/main',
    'edycjakoncertw' => 'site/concert-edit-select',
    'edycjakoncert' => 'site/concert-edit-form',
    'edytujkoncert' => 'site/concert-edit',

    'news_dodaj_form' => 'site/news-add-form',
    'news_dodaj' => 'site/news-add-script',

    'week_dodaj_form' => 'site/week-add-form',
    'week_dodaj' => 'site/week-add-script',
    'lala_dodaj_form' => 'site/lala-add-form',
    'lala_dodaj' => 'site/lala-add-script',
    'concert_dodaj_form' => 'site/concert-add-form',
    'concert_dodaj' => 'site/concert-add',
);

if (($s == '')) {
    $s = 'login';
}

// if $s jest ktoryms ktorych trzeba szukac to search
// else podstawiamy stalego HTML
// a co z lista albumow, bo z TOP 10
if (isset($s_arr[$s])) {
    if (file_exists($s_arr[$s] . '.php')) {
        include $s_arr[$s] . '.php';
    } else {
        $smarty->assign('errmsg', 'Brak pliku: "<i>' . $s_arr[$s] . '.php</i>"');
        // tutaj mozna dac template wyswietlajacy blad a nie MAIN
        $smarty->assign('body_template', 'site/main.tpl');
    }
} else {
    include 'security/hackme.php';
}
