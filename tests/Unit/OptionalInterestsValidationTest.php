<?php

namespace Tests\Unit;

use App\Http\Requests\ProfileRequest;
use App\Http\Requests\UpdateActivitiesRequest;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\PresenceVerifierInterface;
use PHPUnit\Framework\TestCase;

class OptionalInterestsValidationTest extends TestCase
{
    private function validator(): Factory
    {
        $factory = new Factory(new Translator(new ArrayLoader(), 'en'));
        $presence = $this->createMock(PresenceVerifierInterface::class);
        $presence->method('getCount')->willReturnCallback(
            fn ($table, $column, $value) => in_array($value, [1, 'Gardening'], true) ? 1 : 0
        );
        $factory->setPresenceVerifier($presence);

        return $factory;
    }

    public function test_profile_interests_allow_zero_or_one_but_reject_unknown_names(): void
    {
        $request = ProfileRequest::create('/profile/1', 'PATCH');
        $rules = $request->rules();

        foreach ([[], ['Gardening']] as $interests) {
            $validator = $this->validator()->make(['category' => $interests], $rules);
            $this->assertTrue($validator->passes(), $validator->errors()->toJson());
        }

        $this->assertTrue($this->validator()->make(['category' => ['Unknown']], $rules)->fails());
        $this->assertTrue($this->validator()->make(['category' => 'Gardening'], $rules)->fails());
    }

    public function test_activity_updates_can_clear_interests_but_require_an_explicit_array(): void
    {
        $rules = (new UpdateActivitiesRequest())->rules();
        foreach ([[], ['Gardening']] as $interests) {
            $this->assertTrue($this->validator()->make(['user_id' => 1, 'activities' => $interests], $rules)->passes());
        }
        foreach ([['user_id' => 1], ['user_id' => 1, 'activities' => null], ['user_id' => 1, 'activities' => ['Unknown']]] as $input) {
            $this->assertTrue($this->validator()->make($input, $rules)->fails());
        }
    }
}
