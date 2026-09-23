<?php

namespace Tests\Feature;

use App\Livewire\Profile\NotificationSettings;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\Channels\TelegramChannel;
use App\Notifications\LowStockNotification;
use App\Support\NotificationPreferences;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_save_personal_channels_and_chat_id_is_encrypted(): void
    {
        config()->set('services.telegram.enabled', true);
        $user = User::factory()->create();
        $preferences = NotificationPreferences::defaults();
        $preferences['low_stock'] = ['database' => false, 'mail' => true, 'telegram' => true];

        Livewire::actingAs($user)->test(NotificationSettings::class)
            ->set('preferences', $preferences)
            ->set('telegramChatId', '123456789')
            ->call('save')->assertHasNoErrors();

        $setting = $user->fresh()->notificationPreference;
        $this->assertSame('123456789', $setting->telegram_chat_id);
        $this->assertNotSame('123456789', $setting->getRawOriginal('telegram_chat_id'));
        $this->assertSame(['mail', TelegramChannel::class], NotificationPreferences::channels($user->fresh(), 'low_stock'));
    }

    public function test_telegram_requires_a_personal_chat_id(): void
    {
        config()->set('services.telegram.enabled', true);
        $user = User::factory()->create();
        $preferences = NotificationPreferences::defaults();
        $preferences['new_reservation']['telegram'] = true;

        Livewire::actingAs($user)->test(NotificationSettings::class)
            ->set('preferences', $preferences)->set('telegramChatId', '')
            ->call('save')->assertHasErrors(['telegramChatId']);
    }

    public function test_low_stock_notification_uses_the_selected_channels(): void
    {
        $user = User::factory()->create();
        $user->notificationPreference()->create([
            'preferences' => [...NotificationPreferences::defaults(),
                'low_stock' => ['database' => true, 'mail' => true, 'telegram' => false]],
        ]);
        $product = Product::query()->create([
            'name' => 'Tomates', 'slug' => 'tomates', 'sku' => 'TOM-1',
            'unit' => 'kg', 'minimum_stock' => 5, 'is_active' => true,
        ]);
        $warehouse = Warehouse::query()->create(['name' => 'Principal', 'code' => 'PRI']);
        $notification = new LowStockNotification($product, $warehouse, 2);

        $this->assertSame(['database', 'mail'], $notification->via($user->fresh()));
        $this->assertStringContainsString('Stock bajo', $notification->toMail($user)->subject);
    }
}
