<?php
/**
*
* This file is part of French (Formal Honorifics) Sphinx-for-phpBB translation.
* Copyright (C) 2010 phpBB.fr
*
* This program is free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; version 2 of the License.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License along
* with this program; if not, write to the Free Software Foundation, Inc.,
* 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
*
* fulltext_sphinx [French (Formal Honorifics)]
*
* @package   language
* @author    Maël Soucaze <maelsoucaze@phpbb.fr> (Maël Soucaze) http://www.phpbb.fr/
* @copyright (c) 2005 phpBB Group 
* @license   http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License
* @version   $Id$ 
*
*/

/**
* DO NOT CHANGE
*/
if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

// DEVELOPERS PLEASE NOTE
//
// All language files should use UTF-8 as their encoding and the files must not contain a BOM.
//
// Placeholders can now contain order information, e.g. instead of
// 'Page %s of %s' you can (and should) write 'Page %1$s of %2$s', this allows
// translators to re-order the output of data while ensuring it remains correct
//
// You do not need this where single placeholders are used, e.g. 'Message %d' is fine
// equally where a string contains only two placeholders which are used to wrap text
// in a url you again do not need to specify an order e.g., 'Click %sHERE%s' is fine

$lang = array_merge($lang, array(
	'FULLTEXT_SPHINX_AUTOCONF'				=> 'Configurer automatiquement Sphinx',
	'FULLTEXT_SPHINX_AUTOCONF_EXPLAIN'		=> 'Ceci est la manière la plus facile afin d’installer Sphinx. Configurez ici les réglages et un fichier de configuration vous sera écrit. Cela demande que le répertoire de configuration détienne les permissions en écriture nécessaires.',
	'FULLTEXT_SPHINX_AUTORUN'				=> 'Exécuter automatiquement Sphinx',
	'FULLTEXT_SPHINX_AUTORUN_EXPLAIN'		=> 'Ceci est la manière la plus facile afin d’exécuter Sphinx. Sélectionnez les chemins dans cette fenêtre de dialogue et le programme en attente (plus connu sous le nom de “daemon”) de Sphinx démarrera et s’arrêtera lorsque vous le désirerez. Vous pouvez également créer un index depuis le PCA. Si votre installation de PHP empêche l’utilisation des exécutables, vous pouvez désactiver ceci et exécuter manuellement Sphinx.',
	'FULLTEXT_SPHINX_BIN_PATH'				=> 'Chemin vers le répertoire des exécutables',
	'FULLTEXT_SPHINX_BIN_PATH_EXPLAIN'		=> 'Ignorez cela si l’exécution automatique est désactivée. Si ce chemin ne peut pas être déterminé automatiquement, vous devrez saisir le chemin vers le répertoire où sont stockés les exécutables <samp>indexer</samp> et <samp>searchd</samp> de Sphinx.',
	'FULLTEXT_SPHINX_CONFIG_PATH'			=> 'Chemin vers le répertoire de configuration',
	'FULLTEXT_SPHINX_CONFIG_PATH_EXPLAIN'	=> 'Ignorez cela si la configuration automatique est désactivée. Vous devriez créer ce répertoire de configuration dans une destination qui n’est pas disponible sur Internet. Il doit être accessible en écriture par l’utilisateur qui exécute votre serveur Internet (souvent “www-data” ou “nobody”).',
	'FULLTEXT_SPHINX_CONFIGURE_FIRST'		=> 'Avant de créer un index, vous devez activer et configurer Sphinx dans GÉNÉRAL -> CONFIGURATION DU SERVEUR -> Réglages de la recherche.',
	'FULLTEXT_SPHINX_CONFIGURE_BEFORE'		=> 'Configurez les réglages suivants AVANT l’activation de Sphinx',
	'FULLTEXT_SPHINX_CONFIGURE_AFTER'		=> 'Les réglages suivants ne doivent pas obligatoirement être configurés avant l’activation de Sphinx',
	'FULLTEXT_SPHINX_DATA_PATH'				=> 'Chemin vers le répertoire de données',
	'FULLTEXT_SPHINX_DATA_PATH_EXPLAIN'	=> 'Ignorez cela si l’exécution automatique est désactivée. Vous devriez créer ce répertoire dans une destination qui n’est pas disponible sur Internet. Il doit être accessible en écriture par l’utilisateur qui exécute votre serveur Internet (souvent “www-data” ou “nobody”). Il sera utilisé afin de stocker les index et les fichiers journaux.',
	'FULLTEXT_SPHINX_DELTA_POSTS'			=> 'Nombre de messages fréquemment mis à jour dans l’index delta',
	'FULLTEXT_SPHINX_DIRECTORY_NOT_FOUND'	=> 'Le répertoire <strong>%s</strong> n’existe pas. Veuillez corriger les réglages de votre chemin.',
	'FULLTEXT_SPHINX_FILE_NOT_EXECUTABLE'	=> 'Le fichier <strong>%s</strong> n’est pas exécutable par le serveur Internet.',
	'FULLTEXT_SPHINX_FILE_NOT_FOUND'		=> 'Le fichier <strong>%s</strong> n’existe pas. Veuillez corriger les réglages de votre chemin.',
	'FULLTEXT_SPHINX_FILE_NOT_WRITABLE'	=> 'Le fichier <strong>%s</strong> ne peut pas être écrit par le serveur Internet.',
	'FULLTEXT_SPHINX_INDEXER_MEM_LIMIT'	=> 'Limite de mémoire de l’indexeur',
	'FULLTEXT_SPHINX_INDEXER_MEM_LIMIT_EXPLAIN'	=> 'Ce nombre devrait toujours être plus bas que la quantité de mémoire vive disponible sur votre machine. Si vous rencontrez des problèmes de performance, cela vient sûrement de l’indexeur qui consomme trop de ressources. Pour résoudre ce problème, baissez sa quantité de mémoire allouée.',
	'FULLTEXT_SPHINX_LAST_SEARCHES'		=> 'Requêtes de recherche récentes',
	'FULLTEXT_SPHINX_MAIN_POSTS'			=> 'Nombre de messages dans l’index principal',
	'FULLTEXT_SPHINX_PORT'					=> 'Port du programme en attente de la recherche de Sphinx',
	'FULLTEXT_SPHINX_PORT_EXPLAIN'			=> 'Port dans lequel les données du programme en attente de la recherche de Sphinx sur l’hôte local (plus connu sous le nom de “localhost”) transitent. Laissez cela vide afin d’utiliser le port par défaut 3312',
	'FULLTEXT_SPHINX_REQUIRES_EXEC'		=> 'Le plugin Sphinx pour phpBB requiert que la fonction <code>exec</code> de PHP soit désactivée sur votre système.',
	'FULLTEXT_SPHINX_UNCONFIGURED'			=> 'Veuillez régler toutes les options nécessaires dans la section “Fulltext Sphinx”, situées dans la page précédente, avant d’essayer d’activer le plugin Sphinx.',
	'FULLTEXT_SPHINX_WRONG_DATABASE'		=> 'Le plugin Sphinx pour phpBB ne supporte actuellement que MySQL',
	'FULLTEXT_SPHINX_STOPWORDS_FILE'		=> 'Filtre de mots activé',
	'FULLTEXT_SPHINX_STOPWORDS_FILE_EXPLAIN'	=> 'Ce réglage ne fonctionne que lorque l’exécution automatique est activée. Vous pouvez inclure dans votre répertoire de configuration un fichier appelé sphinx_stopwords.txt contenant un mot sur chaque ligne. Si ce fichier est présent, ces mots seront exclus lors du processus d’indexation.',
));

?>