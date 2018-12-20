<?php namespace Carwash;

use Faker\Generator;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ScrubTest extends TestCase
{
    use DatabaseTransactions;

    public function testThatDesiredUserDataGetsScrubbed()
    {
        $this->addConfig();
        $this->addUser([
            'id' => 1,
            'first_name' => 'George',
            'last_name' => 'Costanza',
            'email' => 'gcostanza@hotmail.com',
        ]);
        $this->addUser([
            'id' => 2,
            'first_name' => 'Cosmo',
            'last_name' => 'Kramer',
            'email' => 'cosmo@kramerica.com',
        ]);

        $this->artisan('carwash:scrub');

        $user1 = $this->findUser(1);
        $this->assertNotEquals('George', $user1->first_name);
        $this->assertNotEquals('Costanza', $user1->last_name);
        $this->assertNotEquals('gcostanza@hotmail.com', $user1->email);
        $user2 = $this->findUser(2);
        $this->assertNotEquals('Cosmo', $user2->first_name);
        $this->assertNotEquals('Kramer', $user2->last_name);
        $this->assertNotEquals('cosmo@kramerica.com', $user2->email);
    }

    public function testThatFormattersCanBeAnInvokableClass()
    {
        $formatter = new class ($this)
        {
            private $test;

            public function __construct(TestCase $test)
            {
                $this->test = $test;
            }

            public function __invoke($faker, $attribute)
            {
                $this->test->assertEquals('George', $attribute);

                return 'Foo';
            }
        };

        $this->app->config['carwash'] = [
            'users' => [
                'first_name' => $formatter,
            ],
        ];

        $this->addUser([
            'id' => 1,
            'first_name' => 'George',
            'last_name' => 'Costanza',
            'email' => 'gcostanza@hotmail.com',
        ]);

        $this->artisan('carwash:scrub');

        $user1 = $this->findUser(1);
        $this->assertEquals('Foo', $user1->first_name);
    }

    public function testThatArgumentsCanBePassedToFormatters()
    {
        $this->app->config['carwash'] = [
            'users' => [
                'first_name' => 'words:3,true',
            ],
        ];
        $this->addUser([
            'id' => 1,
            'first_name' => 'George',
            'last_name' => 'Costanza',
            'email' => 'gcostanza@hotmail.com',
        ]);

        $this->artisan('carwash:scrub');

        $user1 = $this->findUser(1);

        $this->assertEquals(3, str_word_count($user1->first_name));
    }

    public function testThatTheTableConfigurationCanBeAnInvokableClass()
    {
        $user = [
            'id' => 1,
            'first_name' => 'George',
            'last_name' => 'Costanza',
            'email' => 'gcostanza@hotmail.com',
        ];

        $this->app['config']['carwash'] = [
            'users' => new class ($this, $user)
            {
                private $test;
                private $user;

                public function __construct(TestCase $test, array $user)
                {
                    $this->test = $test;
                    $this->user = $user;
                }

                public function __invoke($faker, $record)
                {
                    $this->test->assertInstanceOf(Generator::class, $faker);
                    $this->test->assertArraySubset($this->user, $record);

                    return [
                        'first_name' => 'Foo'
                    ];
                }
            }
        ];

        $this->addUser($user);

        $this->artisan('carwash:scrub');

        $user1 = $this->findUser(1);

        $this->assertEquals('Foo', $user1->first_name);
    }

    public function testThatTheTableConfigurationCanBeAnAnonymousFunction()
    {
        $user = [
            'id' => 1,
            'first_name' => 'George',
            'last_name' => 'Costanza',
            'email' => 'gcostanza@hotmail.com',
        ];

        $this->app['config']['carwash'] = [
            'users' => function ($faker, $record) use ($user) {
                $this->assertInstanceOf(Generator::class, $faker);
                $this->assertArraySubset($user, $record);

                return [
                    'first_name' => 'Foo',
                ];
            }
        ];

        $this->addUser($user);

        $this->artisan('carwash:scrub');

        $user1 = $this->findUser(1);

        $this->assertEquals('Foo', $user1->first_name);
    }

    private function addConfig()
    {
        $this->app->config['carwash'] = [
            'users' => [
                'first_name' => 'firstName',
                'last_name' => 'lastName',
                'email' => 'safeEmail',
                'password' => function ($faker) {
                    return $faker->password;
                },
            ],
            'addresses' => [
                'address' => 'streetAddress',
                'city' => 'city',
                'state' => 'state',
                'country' => 'country',
                'postal_code' => 'postcode',
            ]
        ];
    }

    public function testOnlyRequestedTablesAreScrubbed()
    {
        $this->addConfig();

        $userAttributes = [
            'id' => 1,
            'first_name' => 'George',
            'last_name' => 'Costanza',
            'email' => 'gcostanza@hotmail.com'
        ];
        $this->addUser($userAttributes);

        $addressAttributes = [
            'address' => '1344 Queens Blvd',
            'city' => 'New York',
            'state' => 'New York',
            'country' => 'United States',
            'postal_code' => '11101',
        ];
        $this->addAddress($addressAttributes);

        $this->artisan('carwash:scrub', ['--table' => ['addresses']]);

        $user = $this->findUser(1);

        $this->assertArraySubset($userAttributes, (array)$user);

        $address = $this->findAddress(1);

        $this->assertNotEquals($addressAttributes['address'], $address->address);
        $this->assertNotEquals($addressAttributes['city'], $address->city);
        $this->assertNotEquals($addressAttributes['state'], $address->state);
        $this->assertNotEquals($addressAttributes['country'], $address->country);
        $this->assertNotEquals($addressAttributes['postal_code'], $address->postal_code);
    }

    private function addUser($user)
    {
        \DB::table('users')->insert($user);
    }

    private function findUser($id)
    {
        return \DB::table('users')->find($id);
    }

    private function addAddress($address)
    {
        \DB::table('addresses')->insert($address);
    }

    private function findAddress($id)
    {
        return \DB::table('addresses')->find($id);
    }

}
