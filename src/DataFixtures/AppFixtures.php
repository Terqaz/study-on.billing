<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    /**
     * @param $passwordHasher
     */
    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        $user = (new User())
            ->setEmail('user@example.com')
            ->setRoles(['ROLE_USER'])
            ->setBalance(1000.0)
        ;
        $user->setPassword($this->passwordHasher->hashPassword(
            $user,
            'user_password'
        ));
        $manager->persist($user);

        $user = (new User())
            ->setEmail('admin@example.com')
            ->setRoles(['ROLE_SUPER_ADMIN'])
            ->setBalance(0.0)
        ;
        $user->setPassword($this->passwordHasher->hashPassword(
            $user,
            'admin_password'
        ));
        $manager->persist($user);

        $manager->flush();
    }
}
