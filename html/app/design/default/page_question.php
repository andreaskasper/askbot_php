<?php
/**
 * page_question.php - one question with all its answers.
 *
 * The page is fully server rendered for search engines and readers without
 * JavaScript; a small Vue app on top takes care of voting, comments,
 * accepting and posting an answer without a reload.
 *
 * @param array $params id, slug
 */
i18n::init(__FILE__);

$question = new Question((int)$params["id"]);
if (!$question->exists() || ($question->deleted_at !== null && !MyUser::isModerator())) {
    PageEngine::error(404, __("This question does not exist (any more)."));
}

// Canonical URL - a wrong or missing slug redirects once.
$expectedSlug = (string)$question->slug;
if (($params["slug"] ?? "") !== $expectedSlug) {
    PageEngine::goto($question->url(), 301);
}

$question->countView();

$sort = (string)($_GET["sort"] ?? "votes");
$answerRows = Answer::forQuestion($question->id(), $sort);
$authors = User::loadMany(array_merge([$question->author_id], array_column($answerRows, "author_id")));
$questionComments = Comment::forPost("question", $question->id());
$answerComments = Comment::forPosts("answer", array_column($answerRows, "id"));
$myQuestionVote = Vote::myVotes("question", [$question->id()])[$question->id()] ?? 0;
$myAnswerVotes = Vote::myVotes("answer", array_column($answerRows, "id"));

$vueConfig = [
    "questionId"    => $question->id(),
    "questionScore" => (int)$question->score,
    "questionVote"  => (int)$myQuestionVote,
    "acceptedId"    => $question->accepted_answer_id !== null ? (int)$question->accepted_answer_id : null,
    "isAuthor"      => (int)$question->author_id === MyUser::id(),
    "isFavorite"    => MyUser::isLoggedIn() && (new SQL(0))->cmdvalue('SELECT question_id FROM favorites WHERE user_id = "{0}" AND question_id = "{1}"', [MyUser::id(), $question->id()]) !== null,
    "isSubscribed"  => Subscription::has(MyUser::id(), "question", $question->id()),
    "questionComments" => array_map([Comment::class, "toArray"], $questionComments),
    "answers"       => [],
    "closeReasons"  => Moderation::CLOSE_REASONS,
    "flagReasons"   => Flag::REASONS,
    "canVote"       => MyUser::can("vote_up"),
    "canFlag"       => MyUser::can("flag"),
    "canClose"      => MyUser::can("close"),
    "isModerator"   => MyUser::isModerator(),
    "userId"        => MyUser::id(),
];
foreach ($answerRows as $row) {
    $vueConfig["answers"][] = [
        "id"       => (int)$row["id"],
        "score"    => (int)$row["score"],
        "vote"     => (int)($myAnswerVotes[(int)$row["id"]] ?? 0),
        "authorId" => (int)$row["author_id"],
        "comments" => array_map([Comment::class, "toArray"], $answerComments[(int)$row["id"]] ?? []),
    ];
}

$jsonLd = [
    "@context" => "https://schema.org",
    "@type"    => "QAPage",
    "mainEntity" => [
        "@type"        => "Question",
        "name"         => (string)$question->title,
        "text"         => Markdown::toText((string)$question->body_md, 1000),
        "answerCount"  => (int)$question->answer_count,
        "upvoteCount"  => (int)$question->score,
        "dateCreated"  => gmdate("c", strtotime((string)$question->created_at . " UTC")),
        "author"       => ["@type" => "Person", "name" => ($authors[(int)$question->author_id] ?? null)?->displayName() ?? "anonymous"],
    ],
];
if ($answerRows !== []) {
    $best = $answerRows[0];
    $jsonLd["mainEntity"]["acceptedAnswer"] = [
        "@type"       => "Answer",
        "text"        => Markdown::toText((string)$best["body_md"], 1000),
        "upvoteCount" => (int)$best["score"],
        "url"         => $question->url() . "#answer-" . (int)$best["id"],
    ];
}

PageEngine::html("header", [
    "title"       => (string)$question->title,
    "description" => Markdown::toText((string)$question->body_md, 200),
    "canonical"   => $question->url(),
    "jsonld"      => $jsonLd,
    "noindex"     => (int)$question->score < -2,
]);
?>

<div id="questionApp" v-cloak>
<div class="row g-4">
  <div class="col-lg-9">

    <header class="border-bottom pb-3 mb-3">
      <h1 class="h3"><?= html($question->title) ?></h1>
      <div class="small text-secondary d-flex flex-wrap gap-3">
        <span><?= html(__("asked")) ?> <span title="<?= htmlattr(i18n::date($question->created_at, "Y-m-d H:i")) ?>"><?= html(i18n::ago((string)$question->created_at)) ?></span></span>
        <span><?= html(__("viewed")) ?> <?= html(i18n::shortNumber($question->view_count)) ?> <?= html(__("times")) ?></span>
        <span><?= html(__("active")) ?> <?= html(i18n::ago((string)$question->last_activity_at)) ?></span>
        <?php if ((int)$question->revision > 1) { ?>
          <a class="link-secondary" href="<?= htmlattr($question->url() . "/revisions") ?>"><?= html(sprintf(__("%d revisions"), (int)$question->revision)) ?></a>
        <?php } ?>
      </div>
    </header>

    <?php if ((int)$question->is_closed === 1) { ?>
      <div class="alert alert-warning">
        <strong><?= html(__("This question is closed.")) ?></strong>
        <?= html(Moderation::CLOSE_REASONS[(string)$question->closed_reason] ?? (string)$question->closed_reason) ?>
        <?php if ($question->duplicate_of_id !== null) { ?>
          <a href="<?= htmlattr(Question::permalink((int)$question->duplicate_of_id)) ?>"><?= html(__("See the original question")) ?></a>
        <?php } ?>
      </div>
    <?php } ?>

    <!-- question -->
    <article class="d-flex gap-3 mb-4">
      <div class="vote-column text-center">
        <button class="vote-btn" :class="{active: questionVote === 1}" @click="vote('question', questionId, 1)" title="<?= htmlattr(__("This question is useful")) ?>">
          <i class="fa-solid fa-caret-up"></i>
        </button>
        <div class="vote-score">{{ questionScore }}</div>
        <button class="vote-btn" :class="{active: questionVote === -1}" @click="vote('question', questionId, -1)" title="<?= htmlattr(__("This question needs work")) ?>">
          <i class="fa-solid fa-caret-down"></i>
        </button>
        <button class="vote-btn mt-2" :class="{active: isFavorite}" @click="toggleFavorite" title="<?= htmlattr(__("Bookmark this question")) ?>">
          <i class="fa-solid fa-bookmark"></i>
        </button>
      </div>

      <div class="flex-grow-1 min-w-0">
        <div class="post-body" v-pre><?= $question->body_html ?></div>

        <div class="mt-3">
          <?php foreach ($question->tagList() as $tag) { ?>
            <a class="tag-chip" href="<?= htmlattr(url("questions?tag=" . rawurlencode($tag))) ?>"><?= html($tag) ?></a>
          <?php } ?>
        </div>

        <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mt-3">
          <div class="small d-flex gap-2">
            <?php if (MyUser::can("edit", $question->row())) { ?>
              <a class="link-secondary" href="<?= htmlattr($question->url() . "/edit") ?>"><?= html(__("edit")) ?></a>
            <?php } ?>
            <?php if (MyUser::isLoggedIn()) { ?>
              <button class="btn btn-link btn-sm p-0 link-secondary" @click="openFlag('question', questionId)"><?= html(__("flag")) ?></button>
              <button class="btn btn-link btn-sm p-0 link-secondary" @click="toggleSubscribe">
                {{ isSubscribed ? <?= json_encode(__("unfollow")) ?> : <?= json_encode(__("follow")) ?> }}
              </button>
            <?php } ?>
            <?php if (MyUser::can("close")) { ?>
              <button class="btn btn-link btn-sm p-0 link-secondary" @click="openClose"><?= html((int)$question->is_closed === 1 ? __("reopen") : __("close")) ?></button>
            <?php } ?>
          </div>
          <div class="post-signature">
            <div class="text-secondary mb-1"><?= html(__("asked")) ?> <?= html(i18n::ago((string)$question->created_at)) ?></div>
            <?php $author = $authors[(int)$question->author_id] ?? null; if ($author !== null && $author->exists()) { ?>
              <img src="<?= htmlattr($author->avatar(32)) ?>" width="32" height="32" class="rounded me-1" alt="">
              <a href="<?= htmlattr($author->permalink()) ?>"><?= html($author->displayName()) ?></a>
              <span class="badge text-bg-light"><?= (int)$author->karma ?></span>
            <?php } else { ?>
              <span class="text-secondary"><?= html(__("deleted user")) ?></span>
            <?php } ?>
          </div>
        </div>

        <?php PageEngine::html("box_comments", [
            "post_type" => "question",
            "id_expression" => "questionId",
            "comments_expression" => "questionComments",
        ]); ?>
      </div>
    </article>

    <!-- answers -->
    <h2 class="h5 d-flex justify-content-between align-items-center border-bottom pb-2">
      <span><?= html(sprintf(__("%d answers"), count($answerRows))) ?></span>
      <span class="btn-group btn-group-sm">
        <?php foreach (["votes" => __("Votes"), "oldest" => __("Oldest"), "newest" => __("Newest")] as $key => $label) { ?>
          <a class="btn btn-outline-secondary <?= $sort === $key ? "active" : "" ?>" href="<?= htmlattr(Url::withParams(["sort" => $key])) ?>"><?= html($label) ?></a>
        <?php } ?>
      </span>
    </h2>

    <?php foreach ($answerRows as $index => $row) {
        $answerAuthor = $authors[(int)$row["author_id"]] ?? null;
    ?>
      <article class="d-flex gap-3 py-4 border-bottom answer <?= (int)$row["is_accepted"] === 1 ? "is-accepted" : "" ?>" id="answer-<?= (int)$row["id"] ?>">
        <div class="vote-column text-center">
          <button class="vote-btn" :class="{active: answers[<?= $index ?>].vote === 1}" @click="vote('answer', <?= (int)$row["id"] ?>, 1, <?= $index ?>)">
            <i class="fa-solid fa-caret-up"></i>
          </button>
          <div class="vote-score">{{ answers[<?= $index ?>].score }}</div>
          <button class="vote-btn" :class="{active: answers[<?= $index ?>].vote === -1}" @click="vote('answer', <?= (int)$row["id"] ?>, -1, <?= $index ?>)">
            <i class="fa-solid fa-caret-down"></i>
          </button>
          <button class="accept-btn" :class="{accepted: acceptedId === <?= (int)$row["id"] ?>}"
                  v-if="isAuthor || isModerator || acceptedId === <?= (int)$row["id"] ?>"
                  :disabled="!isAuthor && !isModerator"
                  @click="accept(<?= (int)$row["id"] ?>)" title="<?= htmlattr(__("Accept this answer")) ?>">
            <i class="fa-solid fa-check"></i>
          </button>
        </div>

        <div class="flex-grow-1 min-w-0">
          <div class="post-body" v-pre><?= $row["body_html"] ?></div>

          <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mt-3">
            <div class="small d-flex gap-2">
              <?php if (MyUser::can("edit", $row)) { ?>
                <a class="link-secondary" href="<?= htmlattr(url("answer/" . (int)$row["id"] . "/edit")) ?>"><?= html(__("edit")) ?></a>
              <?php } ?>
              <?php if (MyUser::isLoggedIn()) { ?>
                <button class="btn btn-link btn-sm p-0 link-secondary" @click="openFlag('answer', <?= (int)$row["id"] ?>)"><?= html(__("flag")) ?></button>
              <?php } ?>
              <?php if ((int)$row["revision"] > 1) { ?>
                <a class="link-secondary" href="<?= htmlattr(url("answer/" . (int)$row["id"] . "/revisions")) ?>"><?= html(__("revisions")) ?></a>
              <?php } ?>
            </div>
            <div class="post-signature">
              <div class="text-secondary mb-1"><?= html(__("answered")) ?> <?= html(i18n::ago((string)$row["created_at"])) ?></div>
              <?php if ($answerAuthor !== null && $answerAuthor->exists()) { ?>
                <img src="<?= htmlattr($answerAuthor->avatar(32)) ?>" width="32" height="32" class="rounded me-1" alt="">
                <a href="<?= htmlattr($answerAuthor->permalink()) ?>"><?= html($answerAuthor->displayName()) ?></a>
                <span class="badge text-bg-light"><?= (int)$answerAuthor->karma ?></span>
              <?php } else { ?>
                <span class="text-secondary"><?= html(__("deleted user")) ?></span>
              <?php } ?>
            </div>
          </div>

          <?php PageEngine::html("box_comments", [
              "post_type" => "answer",
              "id_expression" => "answers[" . $index . "].id",
              "comments_expression" => "answers[" . $index . "].comments",
          ]); ?>
        </div>
      </article>
    <?php } ?>

    <!-- your answer -->
    <?php if ((int)$question->is_closed === 1) { ?>
      <div class="alert alert-secondary mt-4"><?= html(__("This question is closed, so it cannot be answered any more.")) ?></div>
    <?php } elseif (!MyUser::isLoggedIn()) { ?>
      <div class="alert alert-light border mt-4">
        <a href="<?= htmlattr(url("account/signin")) ?>"><?= html(__("Sign in")) ?></a>
        <?= html(__("to post an answer.")) ?>
      </div>
    <?php } else { ?>
      <section class="mt-4" id="your-answer">
        <h2 class="h5"><?= html(__("Your answer")) ?></h2>
        <?php PageEngine::html("box_editor", ["model" => "body", "rows" => 10]); ?>
        <button class="btn btn-primary mt-3" :disabled="busy || body.trim().length < 20" @click="postAnswer">
          <i v-if="busy" class="fa-solid fa-spinner fa-spin me-1"></i><?= html(__("Post your answer")) ?>
        </button>
      </section>
    <?php } ?>

  </div>

  <div class="col-lg-3">
    <?php PageEngine::html("box_sidebar", [
        "show" => ["related", "tags", "help"],
        "related" => Question::similar((string)$question->title, 6, $question->id()),
    ]); ?>
  </div>
</div>

<!-- flag dialog -->
<div class="modal fade" ref="flagModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><?= html(__("Flag this post")) ?></h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="form-check" v-for="(label, key) in flagReasons" :key="key">
        <input class="form-check-input" type="radio" :id="'flag-' + key" :value="key" v-model="flagReason">
        <label class="form-check-label" :for="'flag-' + key">{{ label }}</label>
      </div>
      <textarea class="form-control mt-3" rows="2" v-model="flagNote" placeholder="<?= htmlattr(__("Anything a moderator should know?")) ?>"></textarea>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" data-bs-dismiss="modal"><?= html(__("Cancel")) ?></button>
      <button class="btn btn-danger" :disabled="!flagReason || busy" @click="submitFlag"><?= html(__("Flag")) ?></button>
    </div>
  </div></div>
</div>

<!-- close dialog -->
<div class="modal fade" ref="closeModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><?= html(__("Close this question")) ?></h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="form-check" v-for="(label, key) in closeReasons" :key="key">
        <input class="form-check-input" type="radio" :id="'close-' + key" :value="key" v-model="closeReason">
        <label class="form-check-label" :for="'close-' + key">{{ label }}</label>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" data-bs-dismiss="modal"><?= html(__("Cancel")) ?></button>
      <button class="btn btn-warning" :disabled="!closeReason || busy" @click="submitClose"><?= html(__("Vote to close")) ?></button>
    </div>
  </div></div>
</div>
</div>

<script>
const { createApp } = Vue;

createApp({
  data() {
    return Object.assign(<?= json_encode($vueConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, {
      body: "",
      preview: "",
      showPreview: false,
      previewTimer: null,
      busy: false,
      commentFormFor: null,
      commentDraft: "",
      flagTarget: null,
      flagReason: "",
      flagNote: "",
      closeReason: ""
    });
  },

  methods: {
    ago(date) { return askbot.timeAgo(date); },

    async vote(postType, postId, value, answerIndex = null) {
      if (!askbot.user) { location.href = askbot.baseUrl + "/account/signin"; return; }
      try {
        const result = await askbot.api("vote.cast", { post_type: postType, post_id: postId, value: value });
        if (answerIndex === null) {
          this.questionScore = result.score;
          this.questionVote = result.value;
        } else {
          this.answers[answerIndex].score = result.score;
          this.answers[answerIndex].vote = result.value;
        }
      } catch (e) { askbot.toast(e.message, "danger"); }
    },

    async accept(answerId) {
      try {
        const result = await askbot.api("question.accept", { id: this.questionId, answer_id: answerId });
        this.acceptedId = result.accepted ? answerId : null;
        askbot.toast(result.accepted ? <?= json_encode(__("Answer accepted.")) ?> : <?= json_encode(__("Acceptance removed.")) ?>);
      } catch (e) { askbot.toast(e.message, "danger"); }
    },

    async toggleFavorite() {
      try {
        const result = await askbot.api("question.favorite", { id: this.questionId });
        this.isFavorite = result.is_favorite;
      } catch (e) { askbot.toast(e.message, "danger"); }
    },

    async toggleSubscribe() {
      try {
        const result = await askbot.api("question.subscribe", { id: this.questionId });
        this.isSubscribed = result.subscribed;
        askbot.toast(result.subscribed ? <?= json_encode(__("You will be notified about new answers.")) ?> : <?= json_encode(__("Notifications turned off.")) ?>);
      } catch (e) { askbot.toast(e.message, "danger"); }
    },

    openCommentForm(postType, postId) {
      this.commentFormFor = postType + ":" + postId;
      this.commentDraft = "";
    },

    canDeleteComment(comment) {
      return askbot.user && (comment.author.id === this.userId || this.isModerator);
    },

    async submitComment(postType, postId, list) {
      if (this.commentDraft.trim().length < 2) return;
      this.busy = true;
      try {
        const result = await askbot.api("comment.create", { post_type: postType, post_id: postId, body: this.commentDraft });
        list.push(result.comment);
        this.commentDraft = "";
        this.commentFormFor = null;
      } catch (e) { askbot.toast(e.message, "danger"); }
      finally { this.busy = false; }
    },

    async deleteComment(comment, list) {
      if (!confirm(askbot.i18n.confirm)) return;
      try {
        await askbot.api("comment.delete", { id: comment.id });
        list.splice(list.indexOf(comment), 1);
      } catch (e) { askbot.toast(e.message, "danger"); }
    },

    openFlag(postType, postId) {
      this.flagTarget = { postType, postId };
      this.flagReason = "";
      this.flagNote = "";
      bootstrap.Modal.getOrCreateInstance(this.$refs.flagModal).show();
    },

    async submitFlag() {
      this.busy = true;
      try {
        await askbot.api("flag.create", {
          post_type: this.flagTarget.postType,
          post_id: this.flagTarget.postId,
          reason: this.flagReason,
          note: this.flagNote
        });
        bootstrap.Modal.getOrCreateInstance(this.$refs.flagModal).hide();
        askbot.toast(<?= json_encode(__("Thank you, a moderator will look at it.")) ?>);
      } catch (e) { askbot.toast(e.message, "danger"); }
      finally { this.busy = false; }
    },

    openClose() {
      this.closeReason = "";
      bootstrap.Modal.getOrCreateInstance(this.$refs.closeModal).show();
    },

    async submitClose() {
      this.busy = true;
      try {
        const result = await askbot.api("question.close", { id: this.questionId, action: "close", reason: this.closeReason });
        bootstrap.Modal.getOrCreateInstance(this.$refs.closeModal).hide();
        if (result.applied) location.reload();
        else askbot.toast(result.votes + "/" + result.needed + " " + <?= json_encode(__("close votes")) ?>);
      } catch (e) { askbot.toast(e.message, "danger"); }
      finally { this.busy = false; }
    },

    schedulePreview() {
      clearTimeout(this.previewTimer);
      this.previewTimer = setTimeout(this.renderPreview, 400);
    },

    async renderPreview() {
      if (!this.showPreview || this.body.trim() === "") { this.preview = ""; return; }
      try { this.preview = (await askbot.api("site.preview", { body: this.body })).html; }
      catch (e) { /* a failing preview must not block writing */ }
    },

    insertMarkup(before, after) {
      const editor = this.$refs.editor;
      if (!editor) return;
      const start = editor.selectionStart, end = editor.selectionEnd;
      const selected = this.body.substring(start, end);
      this.body = this.body.substring(0, start) + before + selected + after + this.body.substring(end);
      this.$nextTick(() => {
        editor.focus();
        editor.selectionStart = start + before.length;
        editor.selectionEnd = start + before.length + selected.length;
      });
      this.schedulePreview();
    },

    async uploadImage(event) {
      const file = event.target.files[0];
      if (!file) return;
      const body = new FormData();
      body.append("file", file);
      body.append("csrf_token", askbot.csrfToken);
      try {
        const response = await fetch(askbot.baseUrl + "/api/site.upload.json", { method: "POST", body });
        const data = await response.json();
        if (data.err.id !== 0) throw new Error(data.err.msg);
        this.insertMarkup("![](" + data.result.url + ")", "");
      } catch (e) { askbot.toast(e.message, "danger"); }
      finally { event.target.value = ""; }
    },

    async postAnswer() {
      this.busy = true;
      try {
        const result = await askbot.api("answer.create", { question_id: this.questionId, body: this.body });
        location.href = result.url;
      } catch (e) { askbot.toast(e.message, "danger"); }
      finally { this.busy = false; }
    }
  },

  watch: {
    showPreview(value) { if (value) this.renderPreview(); }
  }
}).mount("#questionApp");
</script>

<?php PageEngine::html("footer"); ?>
