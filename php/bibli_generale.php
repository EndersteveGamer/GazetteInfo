<?php

/*********************************************************
 *        Bibliothèque de fonctions génériques
 *
 * Les régles de nommage sont les suivantes.
 * Les noms des fonctions respectent la notation camel case.
 *
 * Ils commencent en général par un terme définisant le "domaine" de la fonction :
 *  aff   la fonction affiche du code html / texte destiné au navigateur
 *  html  la fonction renvoie du code html / texte
 *  bd    la fonction gère la base de données
 *
 * Les fonctions qui ne sont utilisés que dans un seul script
 * sont définies dans le script et les noms de ces fonctions se
 * sont suffixées avec la lettre 'L'.
 *
 *********************************************************/

//____________________________________________________________________________
/**
 * Arrêt du script si erreur de base de données
 *
 * Affichage d'un message d'erreur, puis arrêt du script
 * Fonction appelée quand une erreur 'base de données' se produit :
 *      - lors de la phase de connexion au serveur MySQL ou MariaDB
 *      - ou lorsque l'envoi d'une requête échoue
 *
 * @param array    $err    Informations utiles pour le débogage
 *
 * @return void
 */
function bdErreurExit(array $err):void {
    ob_end_clean(); // Suppression de tout ce qui a pu être déja généré

    echo    '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">',
            '<title>Erreur',
            IS_DEV ? ' base de données': '', '</title>',
            '</head><body>';
    if (IS_DEV){
        // Affichage de toutes les infos contenues dans $err
        echo    '<h4>', $err['titre'], '</h4>',
                '<pre>',
                    '<strong>Erreur mysqli</strong> : ',  $err['code'], "\n",
                    $err['message'], "\n";
        if (isset($err['autres'])){
            echo "\n";
            foreach($err['autres'] as $cle => $valeur){
                echo    '<strong>', $cle, '</strong> :', "\n", $valeur, "\n";
            }
        }
        echo    "\n",'<strong>Pile des appels de fonctions :</strong>', "\n", $err['appels'],
                '</pre>';
    }
    else {
        echo 'Une erreur s\'est produite';
    }

    echo    '</body></html>';

    if (! IS_DEV){
        // Mémorisation des erreurs dans un fichier de log
        $fichier = @fopen('error.log', 'a');
        if($fichier){
            fwrite($fichier, '['.date('d/m/Y').' '.date('H:i:s')."]\n");
            fwrite($fichier, $err['titre']."\n");
            fwrite($fichier, "Erreur mysqli : {$err['code']}\n");
            fwrite($fichier, "{$err['message']}\n");
            if (isset($err['autres'])){
                foreach($err['autres'] as $cle => $valeur){
                    fwrite($fichier,"$cle :\n$valeur\n");
                }
            }
            fwrite($fichier,"Pile des appels de fonctions :\n");
            fwrite($fichier, "{$err['appels']}\n\n");
            fclose($fichier);
        }
    }
    exit(1);        // ==> ARRET DU SCRIPT
}

//____________________________________________________________________________
/**
 * Ouverture de la connexion à la base de données en gérant les erreurs.
 *
 * En cas d'erreur de connexion, une page "propre" avec un message d'erreur
 * adéquat est affiché ET le script est arrêté.
 *
 * @return mysqli  objet connecteur à la base de données
 */
function bdConnect(): mysqli {
    // pour forcer la levée de l'exception mysqli_sql_exception
    // si la connexion échoue
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try{
        $conn = mysqli_connect(BD_SERVER, BD_USER, BD_PASS, BD_NAME);
    }
    catch(mysqli_sql_exception $e){
        $err['titre'] = 'Erreur de connexion';
        $err['code'] = $e->getCode();
        // $e->getMessage() est encodée en ISO-8859-1, il faut la convertir en UTF-8
        $err['message'] = mb_convert_encoding($e->getMessage(), 'UTF-8', 'ISO-8859-1');
        $err['appels'] = $e->getTraceAsString(); //Pile d'appels
        $err['autres'] = array('Paramètres' =>   'BD_SERVER : '. BD_SERVER
                                                    ."\n".'BD_USER : '. BD_USER
                                                    ."\n".'BD_PASS : '. BD_PASS
                                                    ."\n".'BD_NAME : '. BD_NAME);
        bdErreurExit($err); // ==> ARRET DU SCRIPT
    }
    try{
        //mysqli_set_charset() définit le jeu de caractères par défaut à utiliser lors de l'envoi
        //de données depuis et vers le serveur de base de données.
        mysqli_set_charset($conn, 'utf8');
        return $conn;     // ===> Sortie connexion OK
    }
    catch(mysqli_sql_exception $e){
        $err['titre'] = 'Erreur lors de la définition du charset';
        $err['code'] = $e->getCode();
        $err['message'] = mb_convert_encoding($e->getMessage(), 'UTF-8', 'ISO-8859-1');
        $err['appels'] = $e->getTraceAsString();
        bdErreurExit($err); // ==> ARRET DU SCRIPT
        exit(1);
    }
}

//____________________________________________________________________________
/**
 * Envoie une requête SQL au serveur de BdD en gérant les erreurs.
 *
 * En cas d'erreur, une page propre avec un message d'erreur est affichée et le
 * script est arrêté. Si l'envoi de la requête réussit, cette fonction renvoie :
 *      - un objet de type mysqli_result dans le cas d'une requête SELECT
 *      - true dans le cas d'une requête INSERT, DELETE ou UPDATE
 *
 * @param   mysqli              $bd     Objet connecteur sur la base de données
 * @param   string              $sql    Requête SQL
 *
 * @return  mysqli_result|bool          Résultat de la requête
 */
function bdSendRequest(mysqli $bd, string $sql): mysqli_result|bool {
    try{
        return mysqli_query($bd, $sql);
    }
    catch(mysqli_sql_exception $e){
        $err['titre'] = 'Erreur de requête';
        $err['code'] = $e->getCode();
        $err['message'] = $e->getMessage();
        $err['appels'] = $e->getTraceAsString();
        $err['autres'] = array('Requête' => $sql);
        bdErreurExit($err);    // ==> ARRET DU SCRIPT
        exit(1);
    }
}

/**
 *  Protection des sorties (code HTML généré à destination du client).
 *
 *  Fonction à appeler pour toutes les chaines provenant de :
 *      - de saisies de l'utilisateur (formulaires)
 *      - de la bdD
 *  Permet de se protéger contre les attaques XSS (Cross site scripting)
 *  Convertit tous les caractères éligibles en entités HTML, notamment :
 *      - les caractères ayant une signification spéciales en HTML (<, >, ...)
 *      - les caractères accentués
 *
 *  Si on lui transmet un tableau, la fonction renvoie un tableau où toutes les chaines
 *  qu'il contient sont protégées, les autres données du tableau ne sont pas modifiées.
 *
 * @param  array|string  $content   la chaine à protéger ou un tableau contenant des chaines à protéger
 *
 * @return array|string             la chaîne protégée ou le tableau
 */
function htmlProtegerSorties(array|string $content): array|string {
    if (is_array($content)) {
        foreach ($content as &$value) {
            if (is_array($value) || is_string($value)){
                $value = htmlProtegerSorties($value);
            }
        }
        unset ($value); // à ne pas oublier (de façon générale)
        return $content;
    }
    // $content est de type string
    return htmlentities($content, ENT_QUOTES, encoding:'UTF-8');
}


//___________________________________________________________________
/**
 * Contrôle des clés présentes dans les tableaux $_GET ou $_POST - piratage ?
 *
 * Soit $x l'ensemble des clés contenues dans $_GET ou $_POST
 * L'ensemble des clés obligatoires doit être inclus dans $x.
 * De même $x doit être inclus dans l'ensemble des clés autorisées,
 * formé par l'union de l'ensemble des clés facultatives et de
 * l'ensemble des clés obligatoires. Si ces 2 conditions sont
 * vraies, la fonction renvoie true, sinon, elle renvoie false.
 * Dit autrement, la fonction renvoie false si une clé obligatoire
 * est absente ou si une clé non autorisée est présente; elle
 * renvoie true si "tout va bien"
 *
 * @param string    $tabGlobal          'post' ou 'get'
 * @param array     $clesObligatoires   tableau contenant les clés qui doivent obligatoirement être présentes
 * @param array     $clesFacultatives   tableau contenant les clés facultatives
 *
 * @return bool                         true si les paramètres sont corrects, false sinon
 */
function parametresControle(string $tabGlobal, array $clesObligatoires, array $clesFacultatives = []): bool{
    $x = strtolower($tabGlobal) == 'post' ? $_POST : $_GET;

    $x = array_keys($x);
    // $clesObligatoires doit être inclus dans $x
    if (count(array_diff($clesObligatoires, $x)) > 0){
        return false;
    }
    // $x doit être inclus dans
    // $clesObligatoires Union $clesFacultatives
    if (count(array_diff($x, array_merge($clesObligatoires, $clesFacultatives))) > 0){
        return false;
    }
    return true;
}

//___________________________________________________________________
/**
 * Teste si une valeur est une valeur entière
 *
 * @param   mixed    $x     valeur à tester
 *
 * @return  bool     true si entier, false sinon
 */
function estEntier(mixed $x):bool {
    return is_numeric($x) && ($x == (int) $x);
}

//___________________________________________________________________
/**
 * Teste si un entier est compris entre 2 autres
 *
 * Les bornes $min et $max sont incluses.
 *
 * @param   int    $x  valeur à tester
 * @param   int    $min  valeur minimale
 * @param   int    $max  valeur maximale
 *
 * @return  bool   true si $min <= $x <= $max
 */
function estEntre(int $x, int $min, int $max):bool {
    return ($x >= $min) && ($x <= $max);
}

//___________________________________________________________________
/**
 * Renvoie un tableau contenant le nom des mois (utile pour certains affichages)
 *
 * @return array    Tableau à indices numériques contenant les noms des mois
 */
function getArrayMonths() : array {
    return array('Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre');
}

//___________________________________________________________________
/**
 * Vérification des champs texte des formulaires
 * - utilisé par les pages commentaire.php et inscription.php
 *
 * @param  string        $texte     texte à vérifier
 * @param  string        $nom       chaîne à ajouter dans celle qui décrit l'erreur
 * @param  array         $erreurs   tableau dans lequel les erreurs sont ajoutées
 * @param  ?int          $long      longueur maximale du champ correspondant dans la base de données
 * @param  ?string       $expReg    expression régulière que le texte doit satisfaire
 *
 * @return  void
 */
function verifierTexte(string $texte, string $nom, array &$erreurs, ?int $long = null, ?string $expReg = null) : void{
    if (empty($texte)){
        $erreurs[] = "$nom ne doit pas être vide.";
    }
    else {
        if(strip_tags($texte) != $texte){
            $erreurs[] = "$nom ne doit pas contenir de tags HTML.";
        }
        else if ($expReg !== null && ! preg_match($expReg, $texte)){
            $erreurs[] = "$nom n'est pas valide.";
        }
        if ($long !== null && mb_strlen($texte, encoding:'UTF-8') > $long){
            $erreurs[] = "$nom ne peut pas dépasser $long caractères.";
        }
    }
}

//___________________________________________________________________
/**
 * Affiche une ligne d'un tableau permettant la saisie d'un champ input de type 'text', 'password', 'date' ou 'email'
 *
 * La ligne est constituée de 2 cellules :
 * - la 1ère cellule contient un label permettant un "contrôle étiqueté" de l'input
 * - la 2ème cellule contient l'input
 *
 * @param string    $libelle        Le label associé à l'input
 * @param array     $attributs      Un tableau associatif donnant les attributs de l'input sous la forme nom => valeur
 * @param string    $prefixId       Le préfixe utilisé pour l'id de l'input, ce qui donne un id égal à {$prefixId}{$attributs['name']}
 *
 * @return  void
 */
function affLigneInput(string $libelle, array $attributs = array(), string $prefixId = 'text'): void{
    echo    '<tr>',
                '<td><label for="', $prefixId, $attributs['name'], '">', $libelle, '</label></td>',
                '<td><input id="', $prefixId, $attributs['name'], '"';

    foreach ($attributs as $cle => $value){
        echo ' ', $cle, ($value !== null ? "='$value'" : '');
    }
    echo '></td></tr>';
}

/**
 * Applies BBCode formatting to the given string to replace all unicode BBCodes with their HTML counterpart
 * @param string $text The text to format
 * @return void
 */
function BBCodeUnicode(string $text): string {
    $text = preg_replace('/\[#([[:digit:]]*)]/', '&#\1', $text);
    return preg_replace('/\[#x([[:alnum:]]*)]/', '&#x\1', $text);
}

/**
 * Applies BBCode formatting to the given string
 * @param string $text The text to format
 * @return string
 */
function BBCodeProcess(string $text): string {
    $text = BBCodeUnicode($text);
    $tags = [
        'p' => 'p',
        'gras' => 'strong',
        'it' => 'em',
        'citation' => 'blockquote',
        'liste' => 'ul',
        'item' => 'li'
    ];
    foreach ($tags as $key => $value) {
        $text = preg_replace('#\['.$key.'](.*?)\[/'.$key.']#s', '<'.$value.'>\1</'.$value.'>', $text);
    }
    $text = preg_replace('#\[a:(.*?)](.*?)\[/a]#s', '<a href="\1">\2</a>', $text);
    $text = preg_replace('/\[br]/', '<br>', $text);
    $text = preg_replace(
        '/\[widget-deezer:([[:alnum:]]*):([[:alnum:]]*):(.*?) (.*?)]/',
        '<figure><iframe width=\'\1\' height=\'\2\' src=\'\3\' allow=\'encrypted-media; clipboard-write\'></iframe><figcaption>\4</figcaption></figure>',
        $text
    );
    return preg_replace(
        '/\[widget-deezer:([[:alnum:]]*):([[:alnum:]]*):(.*?)]/',
        '<iframe width=\'\1\' height=\'\2\' src=\'\3\' allow=\'encrypted-media; clipboard-write\'></iframe>',
        $text
    );
}

function affBBCodeGuide(): void {
    $tags = [
        '<p>contenu</p>' => '[p]contenu[/p]',
        '<strong>contenu</strong>' => '[gras]contenu[/gras]',
        '<em>contenu</em>' => '[it]contenu[/it]',
        '<blockquote>contenu</blockquote>' => '[citation]contenu[/citation]',
        '<ul>contenu</ul>' => '[liste]contenu[/liste]',
        '<li>contenu</li>' => '[item]contenu[/item]',
        '<a href="url">contenu</a>' => '[a:url]contenu[/a]',
        '<br>' => '[br]',
        '<figure>[br]<iframe width=\'w\' height=\'h\' src=\'url\' allow=\'encrypted-media; clipboard-write\'></iframe>[br]<figcaption>legende</figcaption>[br]</figure>' => '[widget-deezer:w:h:url legende]',
        '&#NNN' => '[#NNN]',
        '&#xNNN' => '[#xNNN]'
    ];
    echo
        '<section>',
            '<h2>Guide BBCode</h2>',
            '<p>Voici un guide d\'utilisation du BBCode:</p>',
            '<table id="horoscope">',
                '<tr>',
                    '<td>HTML</td>',
                    '<td>BBCode</td>',
                '</tr>';
    foreach ($tags as $key => $value) {
        echo '<tr><td>', BBCodeProcess(htmlProtegerSorties($key)), '</td><td>', htmlProtegerSorties($value), '</td></tr>';
    }
    echo
            '</table>',
        '</section>';
}

const CLE_CHIFFREMENT = 'the end is never the end is never the end is never';

/**
 * Chiffre et signe une valeur
 * @param string $val La valeur à chiffrer
 * @return string La valeur chiffrée
 */
function chiffrerURL(string $val) : string {
    $ivlen = openssl_cipher_iv_length($cipher='aes-128-gcm');
    $iv = openssl_random_pseudo_bytes($ivlen);
    $x = openssl_encrypt($val, $cipher, base64_decode(CLE_CHIFFREMENT), OPENSSL_RAW_DATA, $iv, $tag);
    $x = $iv.$tag.$x;
    $x = base64_encode($x);
    return urlencode($x);
}

/**
 * Déchiffre une valeur chiffrée avec chiffrerURL
 *
 * @param string $x	La valeur à déchiffrer
 *
 * @return string|false	La valeur déchiffrée ou false si erreur
 */
function dechiffrerURL(string $x) : string|false {
    $x = base64_decode($x);
    $ivlen = openssl_cipher_iv_length($cipher='aes-128-gcm');
    $iv = substr($x, 0, $ivlen);
    $taglen = 16;
    $tag = substr($x, $ivlen, $taglen);
    $x = substr($x, $ivlen + $taglen);
    return openssl_decrypt($x, $cipher, base64_decode(CLE_CHIFFREMENT), OPENSSL_RAW_DATA, $iv, $tag);
}

/**
 * Affiche la liste des erreurs présentes dans le tableau
 * @param array $err Le tableau des erreurs à afficher
 * @param string $message Le message d'erreur à afficher
 * @return void
 */
function afficherTabErreurs(array $err, string $message): void {
    echo    '<div class="erreur">', $message,
    '<ul>';
    foreach ($err as $e) {
        echo        '<li>', $e, '</li>';
    }
    echo        '</ul>',
    '</div>';
}

/**
 * Affiche un menu de confirmation
 * @param string $message Le message de confirmation
 * @param string $cancelText Le texte affiché sur le bouton d'annulation
 * @param string $cancelPage La page vers laquelle mène le bouton d'annulation
 * @param string $confirmText Le texte affiché sur le bouton de confirmation
 * @param string $confirmPage La page vers laquelle mène le bouton de confirmation
 * @return void
 */
function affMenuConfirm(
    string $message,
    string $cancelText, string $cancelPage,
    string $confirmText, string $confirmPage
): void {
    echo
        '<div class="popup">',
            '<div>',
                '<p>', $message, '</p>',
                '<table>',
                    '<tr>',
                        '<td>',
                            '<a href="', $cancelPage, '" class="notexternal">',
                                '<input type="submit" value="', $cancelText, '">',
                            '</a>',
                        '</td>',
                        '<td>',
                            '<a href="', $confirmPage, '">',
                                '<input type="submit" value="', $confirmText, '" class="redButton">',
                            '</a>',
                        '</td>',
                    '</tr>',
                '</table>',
            '</div>',
        '</div>';
}
