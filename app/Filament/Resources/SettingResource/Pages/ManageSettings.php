<?php

namespace App\Filament\Resources\SettingResource\Pages;

use App\Core\Models\Setting;
use App\Filament\Resources\SettingResource;
use Filament\Actions;
use Filament\Resources\Pages\Page;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;

class ManageSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = SettingResource::class;
    
    protected string $view = 'filament.resources.setting-resource.pages.manage-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'phone' => Setting::get('phone', ''),
            'email' => Setting::get('email', ''),
            'whatsapp' => Setting::get('whatsapp', ''),
            'app_name' => Setting::get('app_name', config('app.name')),
            'working_application' => Setting::get('working_application', true),
            'facebook' => Setting::get('facebook', ''),
            'twitter' => Setting::get('twitter', ''),
            'instagram' => Setting::get('instagram', ''),
            'linkedin' => Setting::get('linkedin', ''),
            'youtube' => Setting::get('youtube', ''),
        ]);
    }

    protected function form(Schema $schema): Schema
    {
        return $schema
            ->schema($this->getFormSchema())
            ->statePath('data');
    }

    protected function getFormSchema(): array
    {
        return [
            Components\Section::make(__('system.contact_information'))
                ->icon('heroicon-o-phone')
                ->schema([
                    Forms\Components\TextInput::make('phone')
                        ->label(__('system.phone'))
                        ->tel()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('email')
                        ->label(__('system.email'))
                        ->email()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('whatsapp')
                        ->label(__('system.whatsapp'))
                        ->tel()
                        ->maxLength(255),
                ]),

            Components\Section::make(__('system.application_settings'))
                ->icon('heroicon-o-window')
                ->schema([
                    Forms\Components\TextInput::make('app_name')
                        ->label(__('system.app_name'))
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Toggle::make('working_application')
                        ->label(__('system.working_application')),
                ]),

            Components\Section::make(__('system.social_links'))
                ->icon('heroicon-o-share')
                ->schema([
                    Forms\Components\TextInput::make('facebook')
                        ->label(__('system.facebook'))
                        ->url()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('twitter')
                        ->label(__('system.twitter'))
                        ->url()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('instagram')
                        ->label(__('system.instagram'))
                        ->url()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('linkedin')
                        ->label(__('system.linkedin'))
                        ->url()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('youtube')
                        ->label(__('system.youtube'))
                        ->url()
                        ->maxLength(255),
                ]),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('save')
                ->label(__('system.save'))
                ->icon('heroicon-o-check')
                ->action(function () {
                    $data = $this->form->getState();
                    foreach ($data as $key => $value) {
                        $type = $key === 'working_application' ? 'boolean' : 'string';
                        Setting::set($key, $value, $type, $this->getGroupForKey($key));
                    }
                        notify()->success(
                            __('system.settings_saved_successfully'),
                            __('system.changes_have_been_applied')
                        );
                })
                ->requiresConfirmation(false),
        ];
    }

    protected function getGroupForKey(string $key): string
    {
        if (in_array($key, ['phone', 'email', 'whatsapp'])) {
            return 'contact';
        }
        if (in_array($key, ['app_name', 'working_application'])) {
            return 'application';
        }
        if (in_array($key, ['facebook', 'twitter', 'instagram', 'linkedin', 'youtube'])) {
            return 'social';
        }
        return 'general';
    }
}

