<?php

// chargement des bibliothèques de fonctions
require_once('./bibli_gazette.php');
require_once('./bibli_generale.php');

// bufferisation des sorties
ob_start();

// démarrage ou reprise de la session
session_start();

if (!parametresControle('post', [], ['btnComment', 'commentText', 'btnSuppComm', 'commNum'])) sessionExit();
if (!parametresControle('get', ['id'])) sessionExit();

$_GET['id'] = dechiffrerURL($_GET['id']);
if ($_GET['id'] === false) sessionExit();


affEntete('Article');

// génération du contenu de la page
affContenuL();

affPiedDePage();

// envoi du buffer
ob_end_flush();

/**
 * S'occupe de la gestion de l'envoi du commentaire
 * @param mysqli $bd La base de donnée
 * @return void
 */
function processComment(mysqli $bd): void {
    if (!estAuthentifie()) return;
    if (!isset($_POST['btnComment'])) return;
    $text = mysqli_real_escape_string($bd, $_POST['commentText']);
    $date = date('YmdHi');
    $pseudo = $_SESSION['pseudo'];
    $id = $_GET['id'];
    $sql = "INSERT INTO commentaire VALUES (NULL, '$pseudo', '$text', $date, $id)";
    bdSendRequest($bd, $sql);
    header('Location: article.php?id='.chiffrerURL($_GET['id']));
}

/**
 * S'occupe de la gestion de la suppression d'un commentaire
 * @param mysqli $bd La base de donnée
 * @return void
 */
function processSupprComment(mysqli $bd): void {
    if (!estAuthentifie()) return;
    if (!isset($_POST['btnSuppComm'])) return;
    $commNum = mysqli_real_escape_string($bd, $_POST['commNum']);
    $sql = 'DELETE FROM commentaire WHERE coID = "'.$commNum.'"';
    bdSendRequest($bd, $sql);
    header('Location: article.php?id='.chiffrerURL($_GET['id']));
}

/*********************************************************
 *
 * Définitions des fonctions locales de la page
 *
 *********************************************************/
//_______________________________________________________________
/**
 * Affichage du contenu principal de la page
 *
 * @return  void
 */
function affContenuL() : void {
    if (!parametresControle('get', ['id'])) {
        affErreurL('Il faut utiliser une URL de la forme : http://..../php/article.php?id=XXX');
        return; // ==> fin de la fonction
    }

    if (!estEntier($_GET['id'])) {
        affErreurL('L\'identifiant doit être un entier');
        return; // ==> fin de la fonction
    }

    $id = (int)$_GET['id'];

    if ($id <= 0){
        affErreurL('L\'identifiant doit être un entier strictement positif');
        return; // ==> fin de la fonction
    }

    // ouverture de la connexion à la base de données
    $bd = bdConnect();

    // Récupération de l'article, des informations sur son auteur,
    // et de ses éventuelles commentaires
    // $id est un entier, donc pas besoin de le protéger avec mysqli_real_escape_string()
    $sql = "SELECT *
            FROM (article INNER JOIN utilisateur ON arAuteur = utPseudo)
            LEFT OUTER JOIN commentaire ON arID = coArticle
            WHERE arID = $id
            ORDER BY coDate DESC, coID DESC";

    $result = bdSendRequest($bd, $sql);

    // pas d'articles --> fin de la fonction
    if (mysqli_num_rows($result) == 0) {
        affErreurL('L\'identifiant de l\'article n\'a pas été trouvé dans la base de données');
        // Libération de la mémoire associée au résultat de la requête
        mysqli_free_result($result);
        return; // ==> fin de la fonction
    }

    $tab = mysqli_fetch_assoc($result);

    processComment($bd);
    processSupprComment($bd);

    // Fermeture de la connexion au serveur de BdD, réalisée le plus tôt possible
    mysqli_close($bd);

    // Mise en forme du prénom et du nom de l'auteur pour affichage dans le pied du texte de l'article
    // Exemple :
    // - pour 'johNnY' 'bigOUde', cela donne 'J. Bigoude'
    // - pour 'éric' 'merlet', cela donne 'É. Merlet'
    // À faire avant la protection avec htmlentities() à cause des éventuels accents
    $auteur = upperCaseFirstLetterLowerCaseRemainderL(mb_substr($tab['utPrenom'], 0, 1, encoding:'UTF-8')) . '. ' . upperCaseFirstLetterLowerCaseRemainderL($tab['utNom']);

    // ATTENTION : protection contre les attaques XSS
    $auteur = htmlProtegerSorties($auteur);

    // ATTENTION : protection contre les attaques XSS
    $tab = htmlProtegerSorties($tab);

    echo
        '<main id="article">';

    if (estAuthentifie() && $_SESSION['pseudo'] == $tab['arAuteur']) {
        echo
            '<section>',
                '<p>Vous êtes l\'auteur de cet article, <a href="edition.php?id=', chiffrerURL($id), '">cliquez ici pour le modifier</a>.</p>',
            '</section>';
    }

    echo
            '<article>',
                '<h3>', $tab['arTitre'], '</h3>',
                '<img src="../upload/', $tab['arID'], '.jpg" alt="Photo d\'illustration | ', $tab['arTitre'], '">',
                BBCodeProcess($tab['arTexte']),
                '<footer>',
                    'Par <a href="redaction.php#', $tab['utPseudo'], '">', $auteur, '</a>. ',
                    'Publié le ', dateIntToStringL($tab['arDatePubli']),
                    isset($tab['arDateModif']) ? ', modifié le '. dateIntToStringL($tab['arDateModif']) : '',
                '</footer>',
            '</article>';

    //pour accéder une seconde fois au premier enregistrement de la sélection
    mysqli_data_seek($result, 0);

    // Génération du début de la zone de commentaires
    echo '<section>',
            '<h2>Réactions</h2>';

    // s'il existe des commentaires, on les affiche un par un.
    if (isset($tab['coID'])) {
        echo '<ul>';
        while ($tab = mysqli_fetch_assoc($result)) {
            echo '<li>';
            if (estAuthentifie() && $_SESSION['pseudo'] === $tab['coAuteur']){
                echo '<form method="post" action="article.php?id=', chiffrerURL($_GET['id']), '">',
                '<input type="text" name="commNum" value="', $tab['coID'], '" hidden>',
                '<input type="submit" name="btnSuppComm" value="Supprimer le commentaire" class="redButton">',
                '</form>';
            }
            echo    '<p>Commentaire de <strong>', htmlProtegerSorties($tab['coAuteur']),
                        '</strong>, le ', dateIntToStringL($tab['coDate']),
                    '</p>',
                    '<blockquote>', BBCodeUnicode(htmlProtegerSorties($tab['coTexte'])), '</blockquote>',
                '</li>';
        }
        echo '</ul>';
    }
    // sinon on indique qu'il n'y a pas de commentaires
    else echo '<p>Il n\'y a pas de commentaire pour cet article. </p>';

    // Libération de la mémoire associée au résultat de la requête
    mysqli_free_result($result);

    if (!estAuthentifie()) {
        echo
            '<p>',
                '<a href="./connexion.php">Connectez-vous</a> ou <a href="./inscription.php">inscrivez-vous</a> pour ',
                'pouvoir commenter cet article !',
            '</p>';
    }
    else {
        echo
            '<form method="post" action="article.php?id='.chiffrerURL($_GET['id']).'">',
                '<fieldset>',
                    '<legend>Ajoutez un commentaire</legend>',
                    '<textarea name="commentText" rows="5" cols="110" required></textarea>',
                    '<input type="submit" name="btnComment">',
                '</fieldset>',
            '</form>';
    }

    echo
        '</section>',
    '</main>';
}


//_______________________________________________________________
/**
 * Conversion d'une date format AAAAMMJJHHMM au format JJ mois AAAA à HHhMM
 *
 * @param  int      $date   la date à afficher.
 *
 * @return string           la chaîne qui représente la date
 */
function dateIntToStringL(int $date) : string {
    // les champs date (coDate, arDatePubli, arDateModif) sont de type BIGINT dans la base de données
    // donc pas besoin de les protéger avec htmlentities()

    // si un article a été publié avant l'an 1000, ça marche encore :-)
    $minutes = substr($date, -2);
    $heure = (int)substr($date, -4, 2); //conversion en int pour supprimer le 0 de '07' pax exemple
    $jour = (int)substr($date, -6, 2);
    $mois = substr($date, -8, 2);
    $annee = substr($date, 0, -8);

    $months = getArrayMonths();

    return $jour. ' '. mb_strtolower($months[$mois - 1], encoding:'UTF-8'). ' '. $annee . ' à ' . $heure . 'h' . $minutes;
}


//___________________________________________________________________
/**
 * Renvoie une copie de la chaîne UTF8 transmise en paramètre après avoir mis sa
 * première lettre en majuscule et toutes les suivantes en minuscule
 *
 * @param  string   $str    la chaîne à transformer
 *
 * @return string           la chaîne résultat
 */
function upperCaseFirstLetterLowerCaseRemainderL(string $str) : string {
    $str = mb_strtolower($str, encoding:'UTF-8');
    $fc = mb_strtoupper(mb_substr($str, 0, 1, encoding:'UTF-8'));
    return $fc.mb_substr($str, 1, mb_strlen($str), encoding:'UTF-8');
}


//_______________________________________________________________
/**
 * Affichage d'un message d'erreur dans une zone dédiée de la page.
 *
 * @param  string  $message    le message d'erreur à afficher.
 *
 * @return void
 */
function affErreurL(string $message) : void {
    echo
        '<main>',
            '<section>',
                '<h2>Oups, il y a eu une erreur...</h2>',
                '<p>La page que vous avez demandée a terminé son exécution avec le message d\'erreur suivant :</p>',
                '<blockquote>', $message, '</blockquote>',
            '</section>',
        '</main>';
}
