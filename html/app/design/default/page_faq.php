<?php
i18n::init(__FILE__);
PageEngine::html("header", ["title" => __("FAQ")]);
$faq = [
    [__("What should I ask here?"), __("Anything the community can answer with facts and experience. Concrete problems work best.")],
    [__("Why do I need karma?"), __("Karma is a simple trust signal. The more the community trusts you, the more of the site opens up.")],
    [__("How do I get karma?"), sprintf(__("An upvote on an answer gives you %d points, an upvote on a question %d, and an accepted answer %d."), Config::int("karma_answer_upvote"), Config::int("karma_question_upvote"), Config::int("karma_answer_accepted"))],
    [__("Can I edit other people's posts?"), sprintf(__("Yes, from %d karma. Every edit is stored in the revision history."), Config::int("threshold_edit_others"))],
    [__("What happens when a question is closed?"), __("It stays visible and searchable, but it cannot be answered any more. It can be reopened.")],
    [__("How do I format my post?"), __("Markdown: **bold**, *italic*, `code`, > quote, - list, and three backticks for a code block.")],
    [__("Is there an API?"), __("Yes. Every page has a JSON counterpart under /api/, and you can create a personal API key in your account settings.")],
];
?>
<div class="row"><div class="col-lg-8">
  <h1 class="h4 mb-3"><?= html(__("Frequently asked questions")) ?></h1>
  <div class="accordion" id="faqList">
    <?php foreach ($faq as $index => [$question, $answer]) { ?>
      <div class="accordion-item">
        <h2 class="accordion-header">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq<?= $index ?>">
            <?= html($question) ?>
          </button>
        </h2>
        <div id="faq<?= $index ?>" class="accordion-collapse collapse" data-bs-parent="#faqList">
          <div class="accordion-body"><?= html($answer) ?></div>
        </div>
      </div>
    <?php } ?>
  </div>
</div></div>
<?php PageEngine::html("footer"); ?>
