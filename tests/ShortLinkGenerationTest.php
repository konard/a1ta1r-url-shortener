<?php
/**
 * Unit tests for short link generation algorithm.
 * These tests verify the algorithm's behavior without requiring a database connection.
 */

use PHPUnit\Framework\TestCase;
use Shortener\Repositories\LinkRepository;

class ShortLinkGenerationTest extends TestCase
{
    /**
     * @var LinkRepository|\PHPUnit\Framework\MockObject\MockObject
     */
    private $repository;

    protected function setUp(): void
    {
        // Create a partial mock of LinkRepository
        // We mock only the database-related methods
        $pdoMock = $this->createMock(PDO::class);
        $this->repository = $this->getMockBuilder(LinkRepository::class)
            ->setConstructorArgs([$pdoMock])
            ->onlyMethods(['shortLinkExists'])
            ->getMock();
    }

    /**
     * Test that generated short link has exactly 10 characters
     */
    public function testShortLinkHasFixedLength()
    {
        // Mock shortLinkExists to always return false (no collisions)
        $this->repository->method('shortLinkExists')->willReturn(false);

        $shortLink = $this->repository->generateShortLinkById(1);

        $this->assertNotNull($shortLink);
        $this->assertEquals(10, strlen($shortLink), 'Short link must be exactly 10 characters');
    }

    /**
     * Test that generated short links contain only valid characters
     */
    public function testShortLinkContainsOnlyValidCharacters()
    {
        $this->repository->method('shortLinkExists')->willReturn(false);

        $shortLink = $this->repository->generateShortLinkById(1);

        $validPattern = '/^[a-zA-Z0-9]{10}$/';
        $this->assertMatchesRegularExpression(
            $validPattern,
            $shortLink,
            'Short link must contain only alphanumeric characters'
        );
    }

    /**
     * Test that different calls generate different short links (randomness)
     */
    public function testShortLinksAreRandom()
    {
        $this->repository->method('shortLinkExists')->willReturn(false);

        $links = [];
        for ($i = 0; $i < 100; $i++) {
            $links[] = $this->repository->generateShortLinkById($i);
        }

        $uniqueLinks = array_unique($links);
        $this->assertEquals(count($links), count($uniqueLinks), 'Generated links should be unique');
    }

    /**
     * Test that null ID returns null
     */
    public function testNullIdReturnsNull()
    {
        $shortLink = $this->repository->generateShortLinkById(null);

        $this->assertNull($shortLink);
    }

    /**
     * Test collision detection and retry mechanism
     */
    public function testCollisionRetry()
    {
        // First two calls return true (collision), third call returns false
        $this->repository->method('shortLinkExists')
            ->willReturnOnConsecutiveCalls(true, true, false);

        $shortLink = $this->repository->generateShortLinkById(1);

        $this->assertNotNull($shortLink);
        $this->assertEquals(10, strlen($shortLink));
    }

    /**
     * Test that max retries limit is respected
     */
    public function testMaxRetriesExceeded()
    {
        // Always return true (always collision)
        $this->repository->method('shortLinkExists')->willReturn(true);

        $shortLink = $this->repository->generateShortLinkById(1, 5);

        $this->assertNull($shortLink, 'Should return null when max retries exceeded');
    }

    /**
     * Test uniqueness with large number of generated links
     * This tests the probability of collision with 62^10 possible combinations
     */
    public function testStatisticalUniqueness()
    {
        $this->repository->method('shortLinkExists')->willReturn(false);

        $links = [];
        $iterations = 1000;

        for ($i = 0; $i < $iterations; $i++) {
            $links[] = $this->repository->generateShortLinkById($i);
        }

        $uniqueLinks = array_unique($links);
        $this->assertEquals(
            $iterations,
            count($uniqueLinks),
            "All $iterations generated links should be unique"
        );
    }
}
