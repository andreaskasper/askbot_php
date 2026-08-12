<?php
/**
 * page_question_edit.php - reuses the ask form in edit mode.
 */
PageEngine::html("page_ask", ["mode" => "edit", "id" => (int)$params["id"]]);
