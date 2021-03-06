<?php

namespace AppBundle\Tests\Command;

use AppBundle\Command\CronCommand;
use AppBundle\Entity\PastTimeline;
use AppBundle\Entity\User;
use AppBundle\Service\TwitterAPIService;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Phake;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CronCommandTest extends MyKernelTestCase
{
    protected static $fixtures = [__DIR__.'/../DataFixtures/Alice/fixture.yml'];

    /**
     * @var Application
     */
    private $application;

    /**
     * @var CommandTester
     */
    private $commandTester;

    /**
     * @var Command
     */
    private $command;

    private $mockApiResponse = ['timeline_json' => ['id' => 10, 'body' => 'foo']];

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var EntityManager
     */
    private $entityManager;

    protected function setUp()
    {
        self::bootKernel();
        $this->application = new Application(self::$kernel);
        $this->application->add(new CronCommand());
        $this->command = $this->application->find('cron:SaveTargetDateTimeline');
        $this->commandTester = new CommandTester($this->command);
        $this->container = self::$kernel->getCOntainer();
        $this->entityManager = $this->container->get('doctrine.orm.entity_manager');
    }

    /**
     * @ExceptionScenario
     * cron:SaveTargetDateTimeline あ
     *
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidArgumentExpectException()
    {
        $this->commandTester->execute(['command' => $this->command->getName(), 'date' => 'あ']);
    }

    /**
     * @NomalScenario
     * cron:SaveTargetDateTimeline 2020-12-12.
     */
    public function testExpectPersist2020_12_12Tweet()
    {
        $this->setMocks();

        $exitStatus = $this->commandTester->execute(['command' => $this->command->getName(), 'date' => '2020-12-12']);
        $this->assertEquals(0, $exitStatus);

        $user = $this->findFixtureUser();
        $pastTimeLine = $this->findPersistedObj($user);

        $this->assertEquals($pastTimeLine->getUser()->getId(), $user->getId());
        $this->assertEquals(
            $pastTimeLine->getTimeline(),
            $this->mockApiResponse['timeline_json']
        );

        $this->cleanDB($pastTimeLine);
    }

    private function setMocks()
    {
        $mockApi = Phake::mock(TwitterAPIService::class);
        Phake::when($mockApi)->findIdRangeByDate(Phake::anyParameters())->thenReturn($this->mockApiResponse);
        $this->container->set('twitter_api', $mockApi);

        // only exec for single fixture user(malloc007)
        $mockRepository = Phake::mock(EntityRepository::class);
        $fixtureUserArray = $this->findFixtureUserArray();
        Phake::when($mockRepository)->findAll()->thenReturn($fixtureUserArray);

        $mockDoctrine = Phake::mock(Registry::class);
        Phake::when($mockDoctrine)->getRepository('AppBundle:User')->thenReturn($mockRepository);
        $this->container->set('doctrine', $mockDoctrine);
    }

    private function findFixtureUserArray()
    {
        return $this->entityManager->getRepository(
            'AppBundle:User'
        )->findBy(['username' => 'malloc007']);
    }

    private function findFixtureUser()
    {
        return $this->entityManager->getRepository(
            'AppBundle:User'
        )->findOneBy(['username' => 'malloc007']);
    }

    private function findPersistedObj(User $user)
    {
        return $this->entityManager->getRepository(
            'AppBundle:PastTimeline'
        )->findOneBy(
            [
                'user' => $user,
                'date' => new \DateTime('2020-12-12'),
            ]
        );
    }

    private function cleanDB(PastTimeline $pastTimeLine)
    {
        $this->entityManager->remove($pastTimeLine);
        $this->entityManager->flush();
    }
}
