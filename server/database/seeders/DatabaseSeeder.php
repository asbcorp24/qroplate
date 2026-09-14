<?php

namespace Database\Seeders;

use App\Models\Device;
use App\Models\DeviceChannel;
use App\Models\Tariff;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $device = Device::updateOrCreate(
            ['device_id' => 'DEV-000001'],
            [
                'name' => 'Контроллер 1',
                'type' => 'multi_relay',
                'title' => 'Оплата услуг',
                'subtitle' => 'Выберите свободную услугу',
                'description' => 'Один контроллер, четыре независимых платных канала.',
                'location' => 'Тестовая точка',
                'layout' => 'channels',
                'enabled' => true,
            ]
        );

        for ($i = 1; $i <= 4; $i++) {
            $channel = DeviceChannel::updateOrCreate(
                ['device_id_fk' => $device->id, 'channel' => $i],
                ['name' => 'Услуга '.$i, 'status' => 'free', 'enabled' => true]
            );

            Tariff::updateOrCreate(
                ['code' => 'R'.$i.'-15MIN'],
                [
                    'device_channel_id' => $channel->id,
                    'title' => '15 минут',
                    'duration_sec' => 900,
                    'price' => 100.00,
                    'currency' => 'RUB',
                    'enabled' => true,
                    'sort_order' => 10,
                ]
            );
        }
    }
}
