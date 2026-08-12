<?php

use PHPUnit\Framework\TestCase;

/**
 * End to end flow against a real database: ask, answer, comment, vote, accept.
 * Skipped when no MYSQL_HOST is configured.
 */
final class QuestionFlowTest extends TestCase {

    private static ?User $asker = null;
    private static ?User $answerer = null;

    protected function setUp(): void {
        if (!hasDatabase()) $this->markTestSkipped("no database configured");
    }

    private function asker(): User {
        if (self::$asker === null) {
            self::$asker = User::create("test_asker_" . bin2hex(random_bytes(3)), "asker_" . bin2hex(random_bytes(3)) . "@example.com", "a-very-long-password");
        }
        return self::$asker;
    }

    private function answerer(): User {
        if (self::$answerer === null) {
            self::$answerer = User::create("test_answerer_" . bin2hex(random_bytes(3)), "answerer_" . bin2hex(random_bytes(3)) . "@example.com", "a-very-long-password");
            self::$answerer->save(["karma" => 5000]);
        }
        return self::$answerer;
    }

    public function testAskAnswerVoteAccept(): void {
        $asker = $this->asker();
        $answerer = $this->answerer();

        $question = Question::create(
            "How do I test a question flow properly?",
            "I would like to know how the whole flow behaves when it is exercised end to end.",
            ["testing", "php"],
            $asker->id()
        );
        $this->assertGreaterThan(0, $question->id());
        $this->assertSame(["testing", "php"], $question->tagList());

        // The tag counter is maintained on write.
        $this->assertGreaterThanOrEqual(1, (int)(Tag::byName("testing")["question_count"] ?? 0));

        $answer = Answer::create($question->id(), "Write a functional test that walks through every step, like this one does.", $answerer->id());
        $this->assertGreaterThan(0, $answer->id());
        $question->refresh();
        $this->assertSame(1, (int)$question->answer_count);

        // The asker upvotes the answer.
        $_SESSION["user_id"] = $asker->id();
        $this->relogin($asker);
        $asker->save(["karma" => 100]);
        $result = Vote::cast("answer", $answer->id(), 1);
        $this->assertSame(1, $result["score"]);
        $this->assertSame(1, $result["value"]);

        // Voting the same way again retracts the vote.
        $result = Vote::cast("answer", $answer->id(), 1);
        $this->assertSame(0, $result["score"]);
        $this->assertSame(0, $result["value"]);

        // Accepting gives the answerer karma.
        $karmaBefore = (int)(new User($answerer->id()))->karma;
        $question->accept($answer->id(), $asker->id());
        $question->refresh();
        $this->assertSame($answer->id(), (int)$question->accepted_answer_id);
        $this->assertGreaterThan($karmaBefore, (int)(new User($answerer->id()))->karma);

        // Accepting the same answer again unaccepts it.
        $question->accept($answer->id(), $asker->id());
        $question->refresh();
        $this->assertNull($question->accepted_answer_id);

        // Comments are attached to the right post.
        $comment = Comment::create("question", $question->id(), "Could you add the error message?", $asker->id());
        $this->assertArrayHasKey("id", $comment);
        $this->assertCount(1, Comment::forPost("question", $question->id()));

        // Editing writes a revision.
        $question->update(
            "How do I test a question flow properly, really?",
            "I would like to know how the whole flow behaves when it is exercised end to end, in detail.",
            ["testing", "php", "phpunit"],
            $asker->id(),
            "added a tag"
        );
        $this->assertCount(2, Revision::all("question", $question->id()));

        // Soft delete keeps the row but hides it from the list.
        $question->softDelete($asker->id());
        $question->refresh();
        $this->assertNotNull($question->deleted_at);
    }

    public function testSelfVotingIsRejected(): void {
        $asker = $this->asker();
        $asker->save(["karma" => 1000]);
        $this->relogin($asker);

        $question = Question::create(
            "Can I vote on my own question here?",
            "This should not be possible, and the message should say so clearly.",
            ["testing"],
            $asker->id()
        );
        $this->expectException(\RuntimeException::class);
        Vote::cast("question", $question->id(), 1);
    }

    public function testValidationRejectsShortInput(): void {
        $this->expectException(\InvalidArgumentException::class);
        Question::create("too short", "also too short", ["testing"], $this->asker()->id());
    }

    /** MyUser caches the session user, so tests have to reload it explicitly. */
    private function relogin(User $user): void {
        $reflection = new \ReflectionClass(MyUser::class);
        $property = $reflection->getProperty("_user");
        $property->setAccessible(true);
        $property->setValue(null, $user);
        $loaded = $reflection->getProperty("_loaded");
        $loaded->setAccessible(true);
        $loaded->setValue(null, true);
    }
}
