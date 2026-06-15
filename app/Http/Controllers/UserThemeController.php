<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUserThemeRequest;
use App\Services\UserThemeService;
use Illuminate\Http\RedirectResponse;

class UserThemeController extends Controller
{
    public function __construct(private readonly UserThemeService $userThemeService) {}

    public function update(UpdateUserThemeRequest $request): RedirectResponse
    {
        $this->userThemeService->update($request->user(), $request->validated()['theme']);

        return back();
    }
}
