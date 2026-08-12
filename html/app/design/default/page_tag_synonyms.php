<?php
/**
 * page_tag_synonyms.php - synonyms live on the tag wiki page.
 */
PageEngine::goto(url("tags/" . rawurlencode((string)$params["name"]) . "/edit"));
