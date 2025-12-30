<?php
/**
 * Created by PhpStorm.
 * User: Михаил
 * Date: 31.08.2017
 * Time: 16:37
 */

namespace Shortener\Repositories;


use PHPUnit\Runner\Exception;
use Shortener\Models\Link;

class LinkRepository extends BaseRepository
{
    /**
     * @param $user_id
     * @param $full_link
     * @return bool|Link
     */
    public function addLink($user_id, $full_link)
    {
        $link = new Link(0, $user_id, $full_link, '');
        try {
            $this->saveLink($link);
            return $link;
        } catch (\PDOException $ex) {
            return false;
        }
    }

    /**
     * @param $id
     * @return bool|Link
     */
    public function getLinkById($id)
    {
        $stmt = $this->getDb()->prepare("SELECT id, user_id, full_link, short_link FROM Links WHERE id = :i LIMIT 1");
        $stmt->bindParam(':i', $id);
        $stmt->execute();
        $link = $stmt->fetchAll();
        if (isset($link[0]['id'])) {
            return new Link($link[0]['id'], $link[0]['user_id'], $link[0]['full_link'], $link[0]['short_link']);
        } else {
            return false;
        }
    }

    /**
     * @param $user_id
     * @return array|bool
     */
    public function getLinksByUserId($user_id)
    {
        $stmt = $this->getDb()->prepare("SELECT id, user_id, full_link, short_link FROM Links WHERE user_id = :ui");
        $stmt->bindParam(':ui', $user_id);
        $stmt->execute();
        $raw = $stmt->fetchAll();
        if ($raw[0]['id'] != null) {
            $links = array();
            foreach ($raw as $link) {
                $links[] = new Link($link['id'], $link['user_id'], $link['full_link'], $link['short_link']);
            }
            return $links;
        } else {
            return false;
        }
    }

    /**
     * @param $url
     * @return bool|Link
     */
    public function getLinkByShortUrl($url)
    {
        $stmt = $this->getDb()->prepare("SELECT id, user_id, full_link, short_link FROM Links WHERE short_link = :sl LIMIT 1");
        $stmt->bindParam(':sl', $url);
        $stmt->execute();
        $link = $stmt->fetchAll();
        if ($link[0] !== null) {
            return new Link($link[0]['id'], $link[0]['user_id'], $link[0]['full_link'], $link[0]['short_link']);
        } else {
            return false;
        }
    }

    /**
     * @param Link $link
     */
    public function saveLink(Link &$link)
    {
        if ($link != null) {
            if ($link->id === 0) {
                $stmt = $this->getDb()->prepare("INSERT INTO Links (user_id, full_link, short_link) VALUES (:ui, :fl, :sl)");
                $stmt->bindParam(':ui', $link->user_id);
                $stmt->bindParam(':fl', $link->full_link);
                $stmt->bindParam(':sl', $link->short_link);
                $stmt->execute();
                $link->id = $this->getDb()->lastInsertId();
                $link->short_link = $this->generateShortLinkById($link->id);
                $this->saveLink($link);
            } else {
                $stmt = $this->getDb()->prepare("UPDATE Links SET short_link = :sl WHERE id = :i");
                $stmt->bindParam(':sl', $link->short_link);
                $stmt->bindParam(':i', $link->id);
                $stmt->execute();
            }
        }
    }

    /**
     * @param $id
     * @return bool|Link
     */
    public function deleteLinkById($id)
    {
        $link = $this->getLinkById($id);
        if ($link !== false) {
            $stmt = $this->getDb()->prepare("DELETE FROM Links WHERE id = :i LIMIT 1");
            $stmt->bindParam(':i', $id);
            $stmt->execute();
            return $link;
        } else {
            return false;
        }
    }

    /**
     * Generate a unique short link with fixed length of 10 characters.
     * Uses cryptographically secure random generation with collision detection.
     *
     * @param int $id The link ID (used as additional entropy source)
     * @param int $maxRetries Maximum number of retries if collision occurs
     * @return string|null The generated short link or null on failure
     */
    public function generateShortLinkById($id, $maxRetries = 10)
    {
        $symbols = 'qwertyuiopasdfghjklzxcvbnm1234567890QWERTYUIOPASDFGHJKLZXCVBNM';
        $symbolsLength = strlen($symbols);
        $shortLinkLength = 10;

        if ($id === null) {
            return null;
        }

        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            $shortLink = $this->generateRandomString($symbols, $symbolsLength, $shortLinkLength);

            // Check for collision
            if (!$this->shortLinkExists($shortLink)) {
                return $shortLink;
            }
        }

        // If all retries failed, return null to indicate failure
        return null;
    }

    /**
     * Generate a cryptographically secure random string.
     *
     * @param string $symbols The character set to use
     * @param int $symbolsLength Length of the character set
     * @param int $length Desired length of the output string
     * @return string The generated random string
     */
    private function generateRandomString($symbols, $symbolsLength, $length)
    {
        $result = '';
        $randomBytes = random_bytes($length);

        for ($i = 0; $i < $length; $i++) {
            $index = ord($randomBytes[$i]) % $symbolsLength;
            $result .= $symbols[$index];
        }

        return $result;
    }

    /**
     * Check if a short link already exists in the database.
     *
     * @param string $shortLink The short link to check
     * @return bool True if exists, false otherwise
     */
    public function shortLinkExists($shortLink)
    {
        $stmt = $this->getDb()->prepare("SELECT COUNT(*) FROM Links WHERE short_link = :sl");
        $stmt->bindParam(':sl', $shortLink);
        $stmt->execute();
        $count = $stmt->fetchColumn();

        return $count > 0;
    }
}