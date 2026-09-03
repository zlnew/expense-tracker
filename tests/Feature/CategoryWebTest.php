<?php

use App\Enums\CategoryType;
use App\Models\Balance;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('categories index renders categories page with user categories', function () {
    Category::factory()->create(['user_id' => $this->user->id, 'name' => 'Groceries', 'type' => CategoryType::EXPENSE]);
    Category::factory()->create(['user_id' => $this->user->id, 'name' => 'Salary', 'type' => CategoryType::INCOME]);

    $response = $this->get(route('categories.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CategoryList')
            ->has('categories.data')
            ->has('types')
        );
});

test('category can be created as expense and income', function () {
    $resExpense = $this->post(route('categories.store'), [
        'name' => 'Streaming Subscriptions',
        'type' => 'expense',
    ]);
    $resExpense->assertRedirect();
    $this->assertDatabaseHas('categories', [
        'user_id' => $this->user->id,
        'name' => 'Streaming Subscriptions',
        'type' => 'expense',
    ]);

    $resIncome = $this->post(route('categories.store'), [
        'name' => 'Freelance Projects',
        'type' => 'income',
    ]);
    $resIncome->assertRedirect();
    $this->assertDatabaseHas('categories', [
        'user_id' => $this->user->id,
        'name' => 'Freelance Projects',
        'type' => 'income',
    ]);
});

test('category can be updated', function () {
    $category = Category::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Old Name',
        'type' => CategoryType::EXPENSE,
    ]);

    $response = $this->put(route('categories.update', $category), [
        'name' => 'New Category Name',
        'type' => 'expense',
    ]);

    $response->assertRedirect();
    expect($category->fresh()->name)->toBe('New Category Name');
});

test('category without transactions can be deleted', function () {
    $category = Category::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Temporary Category',
    ]);

    $response = $this->delete(route('categories.destroy', $category));

    $response->assertRedirect();
    $this->assertDatabaseMissing('categories', ['id' => $category->id]);
});

test('category with existing transactions cannot be deleted', function () {
    $balance = Balance::factory()->create(['user_id' => $this->user->id]);
    $category = Category::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Food Category',
    ]);

    Transaction::factory()->create([
        'user_id' => $this->user->id,
        'balance_id' => $balance->id,
        'category_id' => $category->id,
    ]);

    $response = $this->delete(route('categories.destroy', $category));

    $response->assertSessionHasErrors();
    $this->assertDatabaseHas('categories', ['id' => $category->id]);
});

test('user cannot update or delete another users category', function () {
    $otherUser = User::factory()->create();
    $otherCategory = Category::factory()->create([
        'user_id' => $otherUser->id,
        'name' => 'Other Category',
    ]);

    $updateRes = $this->put(route('categories.update', $otherCategory), [
        'name' => 'Hacked Category',
        'type' => 'expense',
    ]);
    $updateRes->assertForbidden();

    $deleteRes = $this->delete(route('categories.destroy', $otherCategory));
    $deleteRes->assertForbidden();
});
