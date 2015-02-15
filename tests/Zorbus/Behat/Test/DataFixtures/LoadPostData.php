<?php

namespace Zorbus\Behat\Test\DataFixtures;

use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Zorbus\Behat\Test\Entity\Post;

class LoadPostData implements FixtureInterface
{
    public function load(ObjectManager $objectManager)
    {
        $post = new Post();
        $post->setTitle('One random title');
        $objectManager->persist($post);

        $post = new Post();
        $post->setTitle('Second not so random title');
        $objectManager->persist($post);

        $objectManager->flush();
    }
}