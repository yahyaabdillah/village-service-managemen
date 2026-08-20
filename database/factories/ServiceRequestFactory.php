<?php

namespace Database\Factories;

use App\Models\ServiceRequest;
use App\Models\ServiceType;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceRequestFactory extends Factory
{
    protected $model = ServiceRequest::class;

    public function definition(): array
    {
        return ['request_code' => ServiceRequest::makeRequestCode(), 'service_type_id' => ServiceType::factory(), 'nik' => $this->faker->numerify('################'), 'applicant_name' => $this->faker->name(), 'phone' => $this->faker->phoneNumber(), 'address' => $this->faker->address(), 'status' => 'submitted', 'submitted_at' => now()];
    }
}
