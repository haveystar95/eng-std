<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\ProfileResource;
use App\Models\Profile;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): ProfileResource
    {
        return new ProfileResource($this->profileFor($request));
    }

    public function update(UpdateProfileRequest $request): ProfileResource
    {
        $profile = $this->profileFor($request);
        $profile->fill($request->validated())->save();

        return new ProfileResource($profile);
    }

    private function profileFor(Request $request): Profile
    {
        return Profile::firstOrCreate(['user_id' => $request->user()->id]);
    }
}
