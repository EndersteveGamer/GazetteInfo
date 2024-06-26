<?php
/*********************************************************
 *        Bibliothèque de fonctions spécifiques          *
 *        à l'application La gazette de L-INFO           *
 *********************************************************/

// Force l'affichage des erreurs
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting( E_ALL );

// Phase de développement (IS_DEV = true) ou de production (IS_DEV = false)
const IS_DEV = true;

/** Constantes : les paramètres de connexion au serveur MariaDB */
const BD_NAME = 'gazette_bd';
const BD_USER = 'gazette_user';
const BD_PASS = 'gazette_pass';
const BD_SERVER = 'mariadb-hostname';

// Définit le fuseau horaire par défaut à utiliser. Disponible depuis PHP 5.1
date_default_timezone_set('Europe/Paris');

// limites liées aux tailles des champs de la table utilisateur
const LMAX_PSEUDO = 20;    // taille du champ usLogin de la table utilisateur
const LMAX_NOM = 50;      // taille du champ usNom de la table utilisateur
const LMAX_PRENOM = 60;   // taille du champ usPrenom de la table utilisateur
const LMAX_EMAIL = 255;   // taille du champ usMail de la table utilisateur

const LMIN_PSEUDO = 4;

const AGE_MINIMUM = 18;

const LMIN_PASSWORD = 4;

const LMAX_TITRE_ARTICLE = 150;

//_______________________________________________________________
/**
 * Affichage du début de la page HTML (head + menu + header).
 *
 * @param  string  $titre       le titre de la page (head et h1)
 * @param  string  $prefixe     le préfixe du chemin relatif vers la racine du site
 *
 * @return void
 */
function affEntete(string $titre, string $prefixe = '..') : void {
    echo
        '<!doctype html>',
        '<html lang="fr">',
            '<head>',
                '<meta charset="UTF-8">',
                '<title>La gazette de L-INFO | ', $titre, '</title>',
                '<link rel="stylesheet" type="text/css" href="', $prefixe,'/styles/gazette.css">',
            '</head>',
            '<body>';

    affMenu($prefixe);

    echo        '<header>',
                    '<img src="', $prefixe, '/images/titre.png" alt="Image du titre | La gazette de L-INFO" width="780" height="83">',
                    '<h1>', $titre, '</h1>',
                '</header>';
}

//_______________________________________________________________
/**
 * Affichage du menu de navigation.
 *
 * @param  string  $prefixe     le préfixe du chemin relatif vers la racine du site
 *
 * @return void
 */
function affMenu(string $prefixe = '..') : void {
    echo    '<nav><ul>',
                '<li><a href="', $prefixe, '/index.php">Accueil</a></li>',
                '<li><a href="', $prefixe, '/php/actus.php">Toute l\'actu</a></li>',
                '<li><a href="', $prefixe, '/php/recherche.php">Recherche</a></li>',
                '<li><a href="', $prefixe, '/php/redaction.php">La rédac\'</a></li>';
    if (estAuthentifie()){
        echo    '<li><a href="#">', htmlProtegerSorties($_SESSION['pseudo']),'</a>',
                    '<ul>',
                        '<li><a href="', $prefixe, '/php/compte.php">Mon profil</a></li>',
                        $_SESSION['redacteur'] ? "<li><a href='$prefixe/php/nouveau.php'>Nouvel article</a></li>" : '',
                        '<li><a href="', $prefixe, '/php/deconnexion.php">Se déconnecter</a></li>',
                    '</ul>',
                '</li>';
    }
    else {
        echo    '<li><a href="', $prefixe, '/php/connexion.php">Se connecter</a></li>';
    }
    echo    '</ul></nav>';
}

//_______________________________________________________________
/**
 * Affichage du pied de page.
 *
 * @return  void
 */
function affPiedDePage() : void {

    echo        '<footer>&copy; Licence Informatique - Février 2024 - Tous droits réservés</footer>',
            '</body></html>';
}

//_______________________________________________________________
/**
* Détermine si l'utilisateur est authentifié
*
* @return bool     true si l'utilisateur est authentifié, false sinon
*/
function estAuthentifie(): bool {
    return  isset($_SESSION['pseudo']);
}


//_______________________________________________________________
/**
 * Termine une session et effectue une redirection vers la page transmise en paramètre
 *
 * Cette fonction est appelée quand l'utilisateur se déconnecte "normalement" et quand une
 * tentative de piratage est détectée. On pourrait améliorer l'application en différenciant ces
 * 2 situations. Et en cas de tentative de piratage, on pourrait faire des traitements pour
 * stocker par exemple l'adresse IP, etc.
 *
 * @param string    $page URL de la page vers laquelle l'utilisateur est redirigé
 *
 * @return void
 */
function sessionExit(string $page = '../index.php'): void {

    // suppression de toutes les variables de session
    $_SESSION = array();

    if (ini_get("session.use_cookies")) {
        // suppression du cookie de session
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 86400,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    session_destroy();

    header("Location: $page");
    exit();
}

/**
 * Affiche des boutons radios permettant de sélectionner une civilité
 * @param array $values Le tableau contenant les valeurs préremplies du formulaire
 * @return void
 */
function affRadSexe(array $values): void {
    echo
        '<tr>',
            '<td>Votre civilité :</td>',
            '<td>';
    $radios = ['h' => 'Monsieur', 'f' => 'Madame', 'nb' => 'Non binaire'];
    foreach ($radios as $value => $label){
        echo                '<label><input type="radio" name="radSexe" value="', $value, '"',
        $value === $values['radSexe'] ? ' checked' : '', '> ', $label, '</label> ';
    }
    echo                '</td>',
    '</tr>';
}

function affFormInfoPerso(array $values): void {
    affRadSexe($values);
    affLigneInput('Votre nom :', array('type' => 'text', 'name' => 'nom', 'value' => htmlProtegerSorties($values['nom']), 'required' => null));
    affLigneInput('Votre prénom :', array('type' => 'text', 'name' => 'prenom', 'value' => htmlProtegerSorties($values['prenom']), 'required' => null));
    $naissance = $values['naissance'];
    if (strlen($naissance) != 0) {
        $naissance = substr_replace($naissance, '-', 4, 0);
        $naissance = substr_replace($naissance, '-', 7, 0);
    }
    affLigneInput('Votre date de naissance :', array('type' => 'date', 'name' => 'naissance', 'value' => $naissance, 'required' => null));
    affLigneInput('Votre email :', array('type' => 'email', 'name' => 'email', 'value' => htmlProtegerSorties($values['email']), 'required' => null));
}

/**
 * Vérifie la validité des informations personnelles du formulaire, et ajoute les éventuelles erreurs dans le tableau
 * donné en paramètre
 * @param array $err Le tableau dans lequel seront ajoutées les éventuelles erreurs
 * @return array Les informations personnelles formattées
 */
function verifierInfoPerso(array &$err): array {
    // vérification de la civilité
    if (!isset($_POST['radSexe'])) {
        $err[] = 'Vous devez choisir une civilité.';
    }
    else if (!($_POST['radSexe'] == 'h' || $_POST['radSexe'] == 'f' || $_POST['radSexe'] == 'nb')) {
        sessionExit();
    }

    // vérification des noms et prénoms
    $expRegNomPrenom = '/^[[:alpha:]]([\' -]?[[:alpha:]]+)*$/u';
    $nom = $_POST['nom'] = trim($_POST['nom']);
    $prenom = $_POST['prenom'] = trim($_POST['prenom']);
    verifierTexte($nom, 'Le nom', $err, LMAX_NOM, $expRegNomPrenom);
    verifierTexte($prenom, 'Le prénom', $err, LMAX_PRENOM, $expRegNomPrenom);

    // vérification du format de l'adresse email
    $email = $_POST['email'] = trim($_POST['email']);
    verifierTexte($email, 'L\'adresse email', $err, LMAX_EMAIL);

    // la validation faite par le navigateur en utilisant le type email pour l'élément HTML input
    // est moins forte que celle faite ci-dessous avec la fonction filter_var()
    // Exemple : 'l@i' passe la validation faite par le navigateur et ne passe pas
    // celle faite ci-dessous
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err[] = 'L\'adresse email n\'est pas valide.';

    // vérification de la valeur de $_POST['cbSpam'] si l'utilisateur accepte de recevoir des mails pourris
    if (isset($_POST['cbSpam']) && $_POST['cbSpam'] !== '1'){
        sessionExit();
    }

    // vérification de la date de naissance
    if (empty($_POST['naissance'])){
        $err[] = 'La date de naissance doit être renseignée.';
    }
    else{
        if(! preg_match('/^\\d{4}(-\\d{2}){2}$/u', $_POST['naissance'])){ //vieux navigateur qui ne supporte pas le type date ?
            $err[] = 'la date de naissance doit être au format "AAAA-MM-JJ".';
        }
        else{
            list($annee, $mois, $jour) = explode('-', $_POST['naissance']);
            if (!checkdate($mois, $jour, $annee)) {
                $err[] = 'La date de naissance n\'est pas valide.';
            }
            else if (mktime(0,0,0, $mois, $jour,(int) $annee + AGE_MINIMUM) > time()) {
                $err[] = 'Vous devez avoir au moins '. AGE_MINIMUM. ' ans pour vous inscrire.';
            }
        }
    }

    $dateNaissance = null;
    if (isset($annee) && isset($mois) && isset($jour)) {
        $dateNaissance = (int)$annee * 10000 + (int)$mois * 100 + (int)$jour;
    }

    $civilite = $_POST['radSexe'];

    $mailsPourris = isset($_POST['cbSpam']) ? 1 : 0;

    return [
        'nom' => $nom,
        'prenom' => $prenom,
        'email' => $email,
        'dateNaissance' => $dateNaissance,
        'civilite' => $civilite,
        'mailsPourris' => $mailsPourris
    ];
}



/**
 * Vérifie la validité du mot de passe récupéré dans le formulaire
 * @param array $err Le tableau dans lequel seront ajoutées les éventuelles erreurs
 * @return void
 */
function verifierMotDePasse(array &$err): void {
    // vérification des mots de passe
    $_POST['passe1'] = trim($_POST['passe1']);
    $_POST['passe2'] = trim($_POST['passe2']);
    if ($_POST['passe1'] !== $_POST['passe2']) {
        $err[] = 'Les mots de passe doivent être identiques.';
    }
    $nb = mb_strlen($_POST['passe1'], encoding:'UTF-8');
    if ($nb < LMIN_PASSWORD){
        $err[] = 'Le mot de passe doit être constitué d\'au moins '. LMIN_PASSWORD . ' caractères.';
    }
}

/**
 * Affiche le résumé d'un article
 * @param array $article L'article à afficher
 * @return void
 */
function affArticleResume(array $article): void {
    echo '<article class="resume">';
    if (is_file('../upload/'.$article['arID'].'.jpg')) {
        echo '<img src="../upload/', $article['arID'], '.jpg" alt="Image d\'illustration">';
    }
    else echo '<img src="../images/none.jpg" alt="Pas d\'image disponible">';
    echo '<h3>', htmlProtegerSorties($article['arTitre']), '</h3>';
    echo '<p>', htmlProtegerSorties($article['arResume']), '</p>';
    echo '<footer><a href="article.php?id=', chiffrerURL($article['arID']), '">Lire l\'article</a></footer></article>';
}

/**
 * Affiche une liste d'article, en les classant par date
 * @param array $articles
 * @return void
 */
function affArticles(array $articles): void {
    $prevMonth = null;
    $prevYear = null;

    foreach ($articles as $tab) {
        $date = $tab['arDateModif'] != null ? $tab['arDateModif'] : $tab['arDatePubli'];
        $month = mb_substr($date, 4, 2);
        $year = mb_substr($date, 0, 4);
        if ($month != $prevMonth || $year != $prevYear) {
            if ($month != null) echo '</section>';
            echo
            '<section>',
            '<h2>', getArrayMonths()[$month - 1], ' ', $year, '</h2>';
            $prevMonth = $month;
            $prevYear = $year;
        }
        affArticleResume($tab);
    }

    if ($prevMonth != null) echo '</section>';
}

/**
 * Affiche un formulaire d'édition d'article
 * @param string $titre Le titre de la section
 * @param string $page La page vers laquelle envoyer le formulaire
 * @param string $btnTexte Le texte présent sur le bouton d'envoi
 * @param array|false $err Un tableau contenant les éventuelles précédentes erreurs de création de l'article.
 * Le tableau est vide si l'article a correctement été créé.
 * false si l'article n'a pas encore été créé.
 * @param bool $suppButton Indique si le bouton de suppression de l'article doit être affiché ou non
 * @param string $confirmMessage Le message de confirmation à afficher si le traitement a réussi
 * @param string $arTitre Le précédent titre de l'article
 * @param string $arResume Le précédent résumé de l'article
 * @param string $arContenu Le précédent contenu de l'article
 * @return void
 */
function affEditionArticle(
    string $titre, string $page,
    string $btnTexte, array|false $err,
    bool $suppButton, string $confirmMessage,
    string $arTitre = '', string $arResume = '', string $arContenu = ''
): void {
    echo
        '<main>';
    if (isset($_POST['btnSupprArticle'])) {
        affMenuConfirm(
            'Voulez-vous vraiment supprimer cet article?',
            'Annuler', $_SERVER['HTTP_REFERER'],
            'Confirmer', 'edition.php?id='.chiffrerURL($_GET['id']).'&suppr='.chiffrerURL(1)
        );
    }
    echo
            '<section>',
                '<h2>', $titre, '</h2>';
    if ($err !== false) {
        if (sizeof($err) != 0) afficherTabErreurs($err, 'Les erreurs suivantes ont été relevées dans l\'article');
        else echo '<p class="validationText">', $confirmMessage, '</p>';
    }
    echo
                '<form enctype="multipart/form-data" method="post" action="', $page, '">',
                    '<p>',
                        '<label for="title">Titre de l\'article : </label>',
                        '<input type="text" id="title" name="title" value="', htmlProtegerSorties($arTitre), '" required>',
                    '</p>',
                    '<p>',
                        '<label for="resume">Résumé de l\'article : </label><br>',
                        '<textarea id="resume" name="resume" cols="115" rows="15" ',
                            'placeholder="Le formattage BBCode est disponible ici" required>', htmlProtegerSorties($arResume), '</textarea>',
                    '</p>',
                    '<p>',
                        '<label for="content">Contenu de l\'article : </label><br>',
                        '<textarea id="content" name="content" cols="115" rows="15" ',
                            'placeholder="Le formattage BBCode est disponible ici" required>', htmlProtegerSorties($arContenu), '</textarea>',
                    '</p>',
                    '<p>',
                        '<label for="image">Image d\'illustration de l\'article: </label>',
                        '<input type="file" name="image" id="image">',
                    '</p>',
                    '<p>',
                        '<table', $suppButton ? '' : ' class="centre"', '>',
                            '<tr>',
                                '<td>',
                                    '<input type="submit" name="btnEditArticle" value="', $btnTexte, '">',
                                '</td>';
    if ($suppButton) {
        echo
                                '<td>',
                                    '<input type="submit" name="btnSupprArticle" value="Supprimer l\'article" class="redButton">',
                                '</td>';
    }
    echo
                            '</tr>',
                        '</table>',
                    '</p>',
                '</form>',
            '</section>';
    affBBCodeGuide();
    echo
        '</main>';
}

/**
 * Vérifie que le titre, le résumé et le contenu d'un article soient valides
 * @param array $err Le tableau dans lequel seront ajoutés les messages d'erreurs
 * @param string $titre Le titre de l'article
 * @param string $resume Le résumé de l'article
 * @param string $contenu Le contenu de l'article
 * @return void
 */
function verifierArticle(array &$err, string $titre, string $resume, string $contenu): void {
    if (strlen($titre) == 0) $err[] = 'Le titre ne doit pas être vide';
    if (strlen($titre) > LMAX_TITRE_ARTICLE) $err[] = 'Le titre est trop long';
    if (strlen($resume) == 0) $err[] = 'Le résumé de l\'article ne doit pas être vide';
    if (strlen($contenu) == 0) $err[] = 'Le contenu de l\'article ne doit pas être vide';
    if (preg_match('/<.*?>/', $titre)) $err[] = 'Le titre ne doit pas contenir de balises HTML';
    if (preg_match('/<.*?>/', $resume)) $err[] = 'Le résumé ne doit pas contenir de balises HTML';
    if (preg_match('/<.*?>/', $contenu)) $err[] = 'L\'article ne doit pas contenir de balises HTML';
    traitementImage($err);
}

/**
 * Effectue le traitement de l'image, et affiche une erreur si le traitement échoue
 * @param array $err Le tableau dans lequel seront ajoutés les messages d'erreur
 * @return void
 */
function traitementImage(array &$err): void {
    if (isset($_FILES['image']) && strlen($_FILES['image']['name']) > 0) {
        $file = $_FILES['image'];
        if ($file['error'] != 0 || !@is_uploaded_file($file['tmp_name'])) {
            $err[] = 'Une erreur est survenue lors de l\'upload du fichier';
        }
        else {
            if ($file['size'] > 1024 * 100) $err[] = 'L\'image d\'illustration doit faire moins de 100Ko';
            if (strtolower(substr($file['name'], strrpos($file['name'], '.'))) != '.jpg') {
                $err[] = 'L\'image d\'illustration doit être au format JPG';
            }
            else {
                $type = mime_content_type($file['tmp_name']);
                if ($type != 'image/jpeg' && $type != 'image/jpg') {
                    $err[] = 'L\'image d\'illustration doit être au format JPG';
                }
                else {
                    list($width, $height) = getimagesize($file['tmp_name']);
                    $r = $width / $height;
                    if ($r != 4/3) $err[] = 'L\'image d\'illustration doit être au format 4/3';
                    else {
                        $image = imagecreatefromjpeg($file['tmp_name']);
                        if ($image === false) {
                            $err[] = 'La création de l\'image a échoué';
                            return;
                        }
                        $image = imagescale($image, 248, 186);
                        if ($image === false) {
                            $err[] = 'Le redimensionnement de l\'image a échoué';
                            return;
                        }
                        $success = imagejpeg($image, $file['tmp_name']);
                        if (!$success) $err[] = 'Le déplacement de l\'image redimensionnée a échoué';
                    }
                }
            }
        }
    }
}
