<?php

namespace GitWrapper\Test;

use Symfony\Component\Process\Process;

class GitWorkingCopyTest extends GitWrapperTestCase
{
    /**
     * Creates and initializes the local repository used for testing.
     */
    public function setUp()
    {
        parent::setUp();

        // Create the local repository.
        $this->wrapper->init(self::REPO_DIR, array('bare' => true));

        // Clone the local repository.
        $directory = 'build/test/wc_init';
        $git = $this->wrapper->clone('file://' . realpath(self::REPO_DIR), $directory);
        $git->config('user.email', self::CONFIG_EMAIL);
        $git->config('user.name', self::CONFIG_NAME);

        // Create the initial structure.
        file_put_contents($directory . '/change.me', "unchanged\n");
        $this->filesystem->touch($directory . '/move.me');
        $this->filesystem->mkdir($directory . '/a.directory', 0755);
        $this->filesystem->touch($directory . '/a.directory/remove.me');

        // Initial commit.
        $git
            ->add('*')
            ->commit('Initial commit.')
            ->push('origin', 'master', array('u' => true))
        ;

        // Create a branch, add a file.
        $branch = 'test-branch';
        file_put_contents($directory . '/branch.txt', "$branch\n");
        $git
            ->checkoutNewBranch($branch)
            ->add('branch.txt')
            ->commit('Committed testing branch.')
            ->push('origin', $branch, array('u' => true))
        ;

        // Create a tag of the branch.
        $git
            ->tag('test-tag')
            ->pushTags()
        ;

        $this->filesystem->remove($directory);
    }

    /**
     * Removes the local repository.
     */
    public function tearDown()
    {
        parent::setUp();

        $this->filesystem->remove(self::REPO_DIR);

        if (is_dir(self::WORKING_DIR)) {
            $this->filesystem->remove(self::WORKING_DIR);
        }
    }

    /**
     * Clones the local repo and returns an initialized GitWorkingCopy object.
     *
     * @param string $directory
     *   The directory that the repository is being cloned to, defaults to
     *   "test/wc".
     *
     * @return \GitWrapper\GitWorkingCopy
     */
    public function getWorkingCopy($directory = self::WORKING_DIR)
    {
        $git = $this->wrapper->workingCopy($directory);
        $git
            ->cloneRepository('file://' . realpath(self::REPO_DIR))
            ->config('user.email', self::CONFIG_EMAIL)
            ->config('user.name', self::CONFIG_NAME)
            ->clearOutput()
        ;
        return $git;
    }

    /**
     * @expectedException \BadMethodCallException
     */
    public function testCallError()
    {
        $git = $this->getWorkingCopy();
        $git->badMethod();
    }

    public function testIsCloned()
    {
        $git = $this->getWorkingCopy();
        $this->assertTrue($git->isCloned());
    }

    public function testGetOutput()
    {
        $git = $this->getWorkingCopy();

        // Test getting output of a simple status command.
        $output = (string) $git->status();
        $this->assertTrue(strpos($output, 'nothing to commit') !== false);

        // Getting output should clear the buffer.
        $cleared = (string) $git;
        $this->assertEmpty($cleared);
    }

    public function testClearOutput()
    {
        $git = $this->getWorkingCopy();

        // Put stuff in the output buffer.
        $git->status();

        $git->clearOutput();
        $output = $git->getOutput();
        $this->assertEmpty($output);
    }

    public function testHasChanges()
    {
        $git = $this->getWorkingCopy();
        $this->assertFalse($git->hasChanges());

        file_put_contents(self::WORKING_DIR . '/change.me', "changed\n");
        $this->assertTrue($git->hasChanges());
    }

    public function testGetBranches()
    {
        $git = $this->getWorkingCopy();
        $branches = $git->getBranches();

        $this->assertTrue($branches instanceof \GitWrapper\GitBranches);

        // Dumb count checks. Is there a better way to do this?
        $allBranches = 0;
        foreach ($branches as $branch) {
            $allBranches++;
        }
        $this->assertEquals($allBranches, 4);

        $remoteBranches = $branches->remote();
        $this->assertEquals(count($remoteBranches), 3);
    }

    public function testFetchAll()
    {
        $git = $this->getWorkingCopy();

        $output = rtrim((string) $git->fetchAll());

        $this->assertEquals('Fetching origin', $output);
    }

    public function testGitAdd()
    {
        $git = $this->getWorkingCopy();
        $this->filesystem->touch(self::WORKING_DIR . '/add.me');

        $git->add('add.me');

        $match = (bool) preg_match('@A\\s+add\\.me@s', $git->getStatus());
        $this->assertTrue($match);
    }

    public function testGitRm()
    {
        $git = $this->getWorkingCopy();
        $git->rm('a.directory/remove.me');
        $this->assertFalse(is_file(self::WORKING_DIR . '/a.directory/remove.me'));
    }

    public function testGitMv()
    {
        $git = $this->getWorkingCopy();
        $git->mv('move.me', 'moved');

        $this->assertFalse(is_file(self::WORKING_DIR . '/move.me'));
        $this->assertTrue(is_file(self::WORKING_DIR . '/moved'));
    }

    public function testGitBranch()
    {
        $branchName = $this->randomString();

        // Create the branch.
        $git = $this->getWorkingCopy();
        $git->branch($branchName);

        // Get list of local branches.
        $branches = (string) $git->branch();

        // Check that our branch is there.
        $this->assertTrue(strpos($branches, $branchName) !== false);
    }

    public function testGitLog()
    {
        $git = $this->getWorkingCopy();
        $output = (string) $git->log();
        return $this->assertTrue(strpos($output, 'Initial commit.') !== false);
    }

    public function testGitConfig()
    {
        $git = $this->getWorkingCopy();
        $email = rtrim((string) $git->config('user.email'));
        $this->assertEquals('opensource@chrispliakas.com', $email);
    }

    public function testGitTag()
    {
        $tag = $this->randomString();

        $git = $this->getWorkingCopy();
        $git
            ->tag($tag)
            ->pushTag($tag)
        ;

        $tags = (string) $git->tag();
        $this->assertTrue(strpos($tags, $tag) !== false);
    }

    public function testGitReset()
    {
        $git = $this->getWorkingCopy();
        file_put_contents(self::WORKING_DIR . '/change.me', "changed\n");

        $this->assertTrue($git->hasChanges());
        $git->reset(array('hard' => true));
        $this->assertFalse($git->hasChanges());
    }

    public function testGitStatus()
    {
        $git = $this->getWorkingCopy();
        file_put_contents(self::WORKING_DIR . '/change.me', "changed\n");
        $output = (string) $git->status(array('s' => true));
        $this->assertEquals(" M change.me\n", $output);
    }

    public function testGitPull()
    {
        $git = $this->getWorkingCopy();
        $output = (string) $git->pull();
        $this->assertEquals("Already up-to-date.\n", $output);
    }

    public function testGitDiff()
    {
        $git = $this->getWorkingCopy();
        file_put_contents(self::WORKING_DIR . '/change.me', "changed\n");
        $output = (string) $git->diff();
        $this->assertTrue(strpos($output, 'diff --git a/change.me b/change.me') === 0);
    }

    public function testGitGrep()
    {
        $git = $this->getWorkingCopy();
        $output = (string) $git->grep('changed', '--', '*.me');
        $this->assertTrue(strpos($output, 'change.me') === 0);
    }

    public function testGitShow()
    {
        $git = $this->getWorkingCopy();
        $output = (string) $git->show('test-tag');
        $this->assertTrue(strpos($output, 'commit ') === 0);
    }

    public function testGitBisect()
    {
        $git = $this->getWorkingCopy();
        $output = (string) $git->bisect('help');
        $this->assertTrue(stripos($output, 'usage: git bisect') === 0);
    }

    public function testGitRemote()
    {
        $git = $this->getWorkingCopy();
        $output = (string) $git->remote();
        $this->assertEquals(rtrim($output), 'origin');
    }

    public function testRebase()
    {
        $git = $this->getWorkingCopy();
        $git
            ->checkout('test-branch')
            ->clearOutput()
        ;

        $output = (string) $git->rebase('test-branch', 'master');
        $this->assertTrue(strpos($output, 'First, rewinding head') === 0);
    }

    public function testMerge()
    {
        $git = $this->getWorkingCopy();
        $git
            ->checkout('test-branch')
            ->checkout('master')
            ->clearOutput()
        ;

        $output = (string) $git->merge('test-branch');
        $this->assertTrue(strpos($output, 'Updating ') === 0);
    }

    public function testOutputListener()
    {
        $git = $this->getWorkingCopy();

        $listener = new Event\TestOutputListener();
        $git->getWrapper()->addOutputListener($listener);

        $git->status();
        $event = $listener->getLastEvent();

        $expectedType = Process::OUT;
        $this->assertEquals($expectedType, $event->getType());

        $this->assertTrue(stripos($event->getBuffer(), 'nothing to commit') !== false);
    }

    public function testLiveOutput()
    {
        $git = $this->getWorkingCopy();

        // Capture output written to STDOUT and use echo so we can suppress and
        // capture it using normal output buffering.
        stream_filter_register('suppress', '\GitWrapper\Test\StreamSuppressFilter');
        $stdoutSuppress = stream_filter_append(STDOUT, 'suppress');

        $git->getWrapper()->streamOutput(true);
        ob_start();
        $git->status();
        $contents = ob_get_contents();
        ob_end_clean();

        $this->assertTrue(stripos($contents, 'nothing to commit') !== false);

        $git->clearOutput();
        $git->getWrapper()->streamOutput(false);
        ob_start();
        $git->status();
        $empty = ob_get_contents();
        ob_end_clean();

        $this->assertEmpty($empty);

        stream_filter_remove($stdoutSuppress);
    }
}
