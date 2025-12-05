<?php

// Language definitions for frequently used strings
$lang_common = array(

// Text orientation and encoding
'lang_direction'					=>	'Gauche à droite', // ltr (Left-To-Right) or rtl (Right-To-Left)
'lang_identifier'					=>	'fr',

// Number formatting
'lang_decimal_point'				=>	',',
'lang_thousands_sep'				=>	' ',

// Notices
'Bad request'						=>	'Erreur! Le lien que vous avez suivi est incorrect ou obsolète.',
'No view'							=>	'Vous n\'êtes pas autorisé à afficher ces forums.',
'No permission'						=>	'Vous n\'avez pas la permission d\'accéder à cette page.',
'Bad referrer'						=>	'Mauvais csrf_hash. Vous avez été redirigé vers cette page à partir d\'une source non autorisée.',
'Bad csrf hash'						=>	'Mauvais hachage CSRF. Vous avez été redirigé vers cette page à partir d\'une source non autorisée.',
'No cookie'							=>	'Vous semblez vous être connecté avec succès, mais aucun cookie n\'a été défini. Veuillez vérifier vos paramètres et, le cas échéant, activer les cookies pour ce site Web.',
'Pun include extension'  			=>	'Impossible de traiter l\'inclusion d\'utilisateur %s à partir du modèle %s. Les fichiers "%s" ne sont pas autorisés',
'Pun include directory'				=>	'Impossible de traiter l\'inclusion d\'utilisateur %s à partir du modèle %s. La traversée de répertoire n\'est pas autorisée',
'Pun include error'					=>	'Impossible de traiter l\'inclusion d\'utilisateur %s à partir du modèle %s. Il n\'y a pas de fichier de ce type ni dans le répertoire du modèle ni dans le répertoire d\'inclusion de l\'utilisateur',
'No page' => 'This page doesn\'t exist yet. Or doesn\'t exist anymore.',

'Hidden text' => 'Texte caché',
'Show' => 'Afficher',
'Hide' => 'Cacher',

// Miscellaneous
'Announcement'						=>	'Annonce',
'Options'							=>	'Options',
'Submit'							=>	'Envoyer', // "Name" of submit buttons
'Ban message'						=>	'Vous êtes banni de ce forum.',
'Ban message ip'                    =>  'Votre adresse IP est banniede ce forum.',
'Ban message 2'						=>	'L\'interdiction expire à la fin de',
'Ban message 3'						=>	'L\'administrateur ou le modérateur qui vous a banni a laissé le message suivant:',
'Ban message 4'						=>	'Veuillez adresser toute demande de renseignements à l\'administrateur du forum à',
'Never'								=>	'Jamais',
'Today'								=>	'Aujourd\'hui',
'Yesterday'							=>	'hier',
'Info'								=>	'Info', // A common table header
'Go back'							=>	'Retour',
'Maintenance'						=>	'Maintenance',
'Redirecting'						=>	'Redirection',
'Click redirect'					=>	'Cliquez ici si vous ne voulez plus attendre (ou si votre navigateur ne vous redirige pas automatiquement)',
'on'								=>	'Actif', // As in "BBCode is on"
'off'								=>	'Innactif',
'Invalid email'						=>	'L\'adresse e mail que vous avez entré est invalide.',
'Required'							=>	'(Obligatoire)',
'required field'					=>	'est un champ obligatoire dans ce formulaire.', // For javascript form validation
'Last post'							=>	'Dernier message',
'by'								=>	'de', // As in last post by some user
'New posts'							=>	'Nouveaux messages', // The link that leads to the first new post
'New posts info'					=>	'Accéder au premier nouveau message de ce sujet.', // The popup text for new posts links
'Username'							=>	'Nom d\'utilisateur',
'Password'							=>	'Mot de passe',
'Email'								=>	'E-mail',
'Send email'						=>	'Envoyer un e-mail',
'Moderated by'						=>	'Modéré par',
'Registered'						=>	'Inscrit',
'Subject'							=>	'Titre',
'Message'							=>	'Message',
'Topic'								=>	'Sujet',
'Forum'								=>	'Forum',
'Posts'								=>	'Messages',
'Replies'							=>	'Réponses',
'Pages'								=>	'Pages:',
'Page'								=>	'Page %s',
'BBCode'							=>	'BBCode:', // You probably shouldn't change this
'url tag'							=>	'Balise [url]:',
'img tag'							=>	'Balise [img]:',
'Smilies'							=>	'Smileys:',
'and'								=>	'et',
'Image link'						=>	'image', // This is displayed (i.e. <image>) instead of images when "Show images" is disabled in the profile
'wrote'								=>	'a écrit:', // For [quote]'s
'Mailer'							=>	'%s', // As in "MyForums Mailer" in the signature of outgoing emails
'Important information'				=>	'Information important',
'Write message legend'				=>	'Rédigez votre message et envoyez le',
'Previous'							=>	'Précédent',
'Next'								=>	'Suivant',
'Spacer'							=>	'…', // Ellipsis for paginate

// Title
'Title'								=>	'Rang',
'Member'							=>	'Membre', // Default title
'Moderator'							=>	'Moderateur',
'Administrator'						=>	'Administrateur',
'Banned'							=>	'banni',
'Guest'								=>	'Invité',

// Stuff for include/parser.php
'BBCode error no opening tag'		=>	'[/%1$s] a été trouvé sans correspondance [%1$s]',
'BBCode error invalid nesting'		=>	'[%1$s] a été ouvert dans [%2$s], ceci n\'est pas autorisé',
'BBCode error invalid self-nesting'	=>	'[%s] a été ouvert en lui-même, ce n\'est pas permis',
'BBCode error no closing tag'		=>	'[%1$s] a été trouvé sans correspondance [/%1$s]',
'BBCode error empty attribute'		=>	'[%s] la balise avait une section d\'attribut vide',
'BBCode error tag not allowed'		=>	'Vous n\'êtes pas autorisé à utiliser les balises [%s]',
'BBCode error tag url not allowed'	=>	'Vous n\'êtes pas autorisé à publier des liens',
'BBCode list size error'			=>	'Votre liste était trop longue pour être analysée, veuillez la réduire!',

// Stuff for the navigator (top of every page)
'Index'								=>	'Accueil',
'User list'							=>	'Liste des Utilisateurs',
'Rules'								=>	'Règlement',
'Search'							=>	'Recherche',
'Register'							=>	'S\'enregistrer',
'Login'								=>	'Se connecter',
'Not logged in'						=>	'Vous n\'êtes pas connecté.',
'Profile'							=>	'Profil',
'Logout'							=>	'Se déconnecter',
'Logged in as'						=>	'connecté en tant que',
'Admin'								=>	'Administration',
'Last visit'						=>	'Dernière visite: %s',
'Topic searches'					=>	'Sujets:',
'New posts header'					=>	'Nouveau',
'Active topics'						=>	'Actif',
'Unanswered topics'					=>	'Sans réponse',
'Posted topics'						=>	'Envoyé',
'Show new posts'					=>	'Rechercher des sujets avec de nouveaux messages depuis votre dernière visite.',
'Show active topics'				=>	'Rechercher des sujets avec des messages récents.',
'Show unanswered topics'			=>	'Rechercher des sujets sans réponses.',
'Show posted topics'				=>	'Rechercher des sujets sur lesquels vous avez publié des messages.',
'Mark all as read'					=>	'Marquer tous les sujets comme lus',
'Mark forum read'					=>	'Marquer ce forum comme lu',
'Title separator'					=>	' / ',
'PM' => 'Boite Privée',
'PMsend' => 'Envoyer un message privé',
'PMnew' => 'Nouveau message privé',
'PMmess' => 'Vous avez de nouveaux messages privés (%s msgs.).',

'Warn' => 'ATTENTION!',
'WarnMess' => 'Vous avez un nouvel avertissement !',

// Stuff for the page footer
'Board footer'						=>	'Pied de page',
'Jump to'							=>	'aller à',
'Go'								=>	' Go ', // Submit button in forum jump
'Moderate topic'					=>	'Sujet modéré',
'All'								=>	'Tout',
'Move topic'						=>	'Déplacer le sujet',
'Open topic'						=>	'Ouvrir le sujet',
'Close topic'						=>	'Fermer le sujet',
'Unstick topic'						=>	'Désépingler le sujet',
'Stick topic'						=>	'Epingler le sujet',
'Moderate forum'					=>	'Forum modéré',
'Powered by' => 'Propulsé par %s<br />Modifié par &#x56;&#x69;&#x73;&#x6D;&#x61;&#x6E; Traduit par N-Studio18',

// Debug information
'Debug table'						=>	'Informations de débogage',
'Querytime'							=>	'Généré en %1$s secondes, %2$s requêtes exécutées',
'Memory usage'						=>	'Utilisation de la mémoire: %1$s',
'Peak usage'						=>	'(Pic : %1$s)',
'Query times'						=>	'Temps (s)',
'Query'								=>	'chaîne de requête',
'Total query time'					=>	'Temps de requête total: %s',

// For extern.php RSS feed
'RSS description'					=>	'Les sujets les plus récents sur %s.',
'RSS description topic'				=>	'Les messages les plus récents dans %s.',
'RSS reply'							=>	'Re: ', // The topic subject will be appended to this string (to signify a reply)
'RSS active topics feed'			=>	'Flux RSS des sujets actifs',
'Atom active topics feed'			=>	'Flux de sujets actifs Atom',
'RSS forum feed'					=>	'Flux RSS du forum',
'Atom forum feed'					=>	'Flux Atom du forum',
'RSS topic feed'					=>	'Flux RSS du sujet',
'Atom topic feed'					=>	'Flux Atomdu sujet',

'After time'	=>   'Ajouté plus tard',
'After time s'	=>   ' s',
'After time i'	=>   ' min',
'After time H'	=>   ' h',
'After time d'	=>   ' d',

// Admin related stuff in the header
'New reports'						=>	'Il y a de nouveaux rapports',
'Maintenance mode enabled'			=>	'Le mode maintenance est activé!',

// Units for file sizes
'Size unit B'						=>	'%s O',
'Size unit KiB'						=>	'%s KiO',
'Size unit MiB'						=>	'%s MiO',
'Size unit GiB'						=>	'%s GiO',
'Size unit TiB'						=>	'%s TiO',
'Size unit PiB'						=>	'%s PiO',
'Size unit EiB'						=>	'%s EiO',

);
