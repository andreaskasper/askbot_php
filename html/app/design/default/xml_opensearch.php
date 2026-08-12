<?php
header("Content-Type: application/opensearchdescription+xml; charset=utf-8");
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearch/1.1/">
  <ShortName><?= html(mb_substr((string)Config::get("site_title"), 0, 16)) ?></ShortName>
  <Description><?= html(Config::get("site_description")) ?></Description>
  <InputEncoding>UTF-8</InputEncoding>
  <Url type="text/html" method="get" template="<?= html(url("search")) ?>?q={searchTerms}"/>
</OpenSearchDescription>
