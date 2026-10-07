<?php

namespace OpenDemat\Core\Discussion;

/**
 * Historique de discussion entre deux parties, stocké comme tableau JSON dans un champ TEXT.
 *
 * Adoption dans une entité :
 *
 *   use DiscussionHistoryTrait;
 *
 *   #[ORM\Column(name: 'mon_champ', type: Types::TEXT, nullable: true)]
 *   protected ?string $discussionHistory = null;
 *
 * Chaque entrée du tableau a la forme : ['auteur' => string, 'message' => string, 'date' => 'Y-m-d H:i:s']
 * Le nom du rôle passé à addDiscussionMessage() est libre (ex. 'ETUDIANT', 'GESTIONNAIRE'…).
 */
trait DiscussionHistoryTrait
{
    private const DISCUSSION_JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    public function getDiscussionHistory(): ?string
    {
        return $this->discussionHistory;
    }

    /** Accepte un tableau JSON valide ou du texte plain-text (legacy → singleton). Idempotent. */
    public function setDiscussionHistory(?string $value): static
    {
        if ($value === null || $value === '') {
            $this->discussionHistory = $value;
            return $this;
        }
        $decoded = json_decode($value, true);
        if (is_array($decoded) && array_is_list($decoded)) {
            $this->discussionHistory = $value;
        } else {
            // Normalise un texte plain-text résiduel (données legacy) en singleton JSON
            $this->discussionHistory = json_encode([
                ['auteur' => '', 'message' => $value, 'date' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')],
            ], self::DISCUSSION_JSON_FLAGS);
        }
        return $this;
    }

    /** Retourne le tableau [{auteur, message, date}]. Normalise le plain-text résiduel à la volée sans persister. */
    public function getDiscussionMessages(): array
    {
        if ($this->discussionHistory === null || $this->discussionHistory === '') {
            return [];
        }
        $decoded = json_decode($this->discussionHistory, true);
        if (is_array($decoded) && array_is_list($decoded)) {
            return $decoded;
        }
        return [['auteur' => '', 'message' => $this->discussionHistory, 'date' => '']];
    }

    public function addDiscussionMessage(string $auteur, string $message): static
    {
        $history   = $this->getDiscussionMessages();
        $history[] = ['auteur' => $auteur, 'message' => $message, 'date' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')];
        $this->discussionHistory = json_encode($history, self::DISCUSSION_JSON_FLAGS);
        return $this;
    }

    /** Normalise une éventuelle chaîne plain-text en singleton JSON (idempotent). */
    public function normalizeDiscussionHistory(): void
    {
        $this->setDiscussionHistory($this->discussionHistory);
    }
}
