<?php
/**
 * box_inbox.php - private messages.
 *
 * @param array $params user
 */
$user = $params["user"];
$to = (int)($_GET["to"] ?? 0);
?>
<div id="inboxApp" v-cloak>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h6 mb-0"><?= html(__("Messages")) ?></h2>
    <button class="btn btn-sm btn-primary" @click="composing = !composing">
      <i class="fa-solid fa-pen me-1"></i><?= html(__("New message")) ?>
    </button>
  </div>

  <div v-if="composing" class="card mb-3"><div class="card-body">
    <div class="row g-2">
      <div class="col-md-4">
        <input class="form-control form-control-sm" v-model="to" placeholder="<?= htmlattr(__("recipient user id")) ?>">
      </div>
      <div class="col-md-8">
        <input class="form-control form-control-sm" v-model="subject" placeholder="<?= htmlattr(__("Subject")) ?>">
      </div>
      <div class="col-12">
        <textarea class="form-control form-control-sm" rows="4" v-model="body" placeholder="<?= htmlattr(__("Your message")) ?>"></textarea>
      </div>
    </div>
    <button class="btn btn-sm btn-primary mt-2" :disabled="busy || !to || !subject || !body" @click="send"><?= html(__("Send")) ?></button>
  </div></div>

  <div class="list-group list-group-flush">
    <?php foreach (Message::inbox($user->id()) as $message) { ?>
      <div class="list-group-item px-0 <?= $message["read_at"] === null ? "fw-semibold" : "" ?>">
        <div class="d-flex justify-content-between">
          <span><?= html($message["subject"]) ?></span>
          <span class="small text-secondary"><?= html(i18n::ago((string)$message["created_at"])) ?></span>
        </div>
        <div class="small text-secondary">
          <?= html(__("from")) ?>
          <a href="<?= htmlattr(url("users/" . (int)$message["from_user_id"] . "/" . ($message["from_slug"] ?? ""))) ?>"><?= html((string)($message["from_name"] ?? __("deleted user"))) ?></a>
        </div>
        <div class="post-body small mt-2" v-pre><?= $message["body_html"] ?></div>
      </div>
    <?php } ?>
  </div>
</div>

<script>
const { createApp } = Vue;
createApp({
  data() { return { composing: <?= $to > 0 ? "true" : "false" ?>, to: <?= json_encode($to > 0 ? (string)$to : "") ?>, subject: "", body: "", busy: false }; },
  methods: {
    async send() {
      this.busy = true;
      try {
        await askbot.api("user.sendmessage", { to: this.to, subject: this.subject, body: this.body });
        askbot.toast(<?= json_encode(__("Message sent.")) ?>);
        this.composing = false; this.subject = ""; this.body = "";
      } catch (e) { askbot.toast(e.message, "danger"); }
      finally { this.busy = false; }
    }
  }
}).mount("#inboxApp");
</script>
