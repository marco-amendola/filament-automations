<?php

namespace Automations\FilamentAutomations\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Illuminate\Support\HtmlString;
use Filament\Tables\Table;
use Automations\FilamentAutomations\Concerns\CanSetupAutomations;
use Automations\FilamentAutomations\Models\Automation;
use Automations\FilamentAutomations\Resources\FilamentAutomationResource\Pages;

class FilamentAutomationResource extends Resource
{
    use CanSetupAutomations;

    protected static ?string $model = Automation::class;

    protected static ?string $navigationIcon = 'heroicon-s-bolt';

    public static function getModelLabel(): string
    {
        return __('filament-automations::automations.title');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-automations::automations.plural_title');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-automations::automations.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament-automations::automations.navigation_group');
    }


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make()->schema([
                    Forms\Components\Tabs\Tab::make('Basic')->label('Generale')->schema([
                        Forms\Components\Select::make('model_type')->label('Modello')->searchable()->reactive()->options(self::getModelTypeOptions())->required(),
                        Forms\Components\Select::make('model_id')->label('Specifica un id')->helperText('Specificando un ID, il automation verrà applicato solo a quel record.')
                            ->searchable()->getSearchResultsUsing(fn(Forms\Get $get, ?string $search) => $get('model_type') ? self::getModelOptions(app($get('model_type')), $search) : [])->nullable(),
                        Forms\Components\TextInput::make('title')->label('Titolo')->autofocus()->required(),
                        Forms\Components\Toggle::make('enabled')->label('Abilitato')->inline(false)->default(true)->required(),
                        Forms\Components\Textarea::make('description')->label('Descrizione')->nullable(),
                    ]),
                    Forms\Components\Tabs\Tab::make('Trigger')->schema([
                        Forms\Components\Repeater::make('trigger')->schema(fn(Forms\Get $get) => [
                            Forms\Components\Select::make('event')->label('Evento')->options([
                                'created' => 'Creato',
                                'updated' => 'Aggiornato',
                                'deleted' => 'Eliminato',
                                'restored' => 'Ripristinato',
                                'forceDeleted' => 'Eliminato definitivamente',
                            ])->reactive()->required(),
                            Forms\Components\Group::make()->schema([
                                Forms\Components\Repeater::make('triggers')
                                    ->helperText('Quando tutte le condizioni sono soddisfatte, l\'azione verrà eseguita.')
                                    ->schema([
                                        Forms\Components\Fieldset::make('Trigger Definition')->schema([
                                            Forms\Components\Select::make('field')->reactive()->label('Il campo')->options(fn() => $get('model_type') ? self::getModelFields(app($get('model_type'))) : [])->nullable(),
                                            Forms\Components\Select::make('operator')->label('è')->options([
                                                'contains' => 'contiene',
                                                '===' => '===',
                                                '==' => '==',
                                                '!=' => '!=',
                                                '!==' => '!==',
                                                '>' => '>',
                                                '<' => '<',
                                                '>=' => '>=',
                                                '<=' => '<=',
                                            ])->nullable(),
                                            Forms\Components\TextInput::make('value')->label('Valore')->nullable(),
                                        ])->columns(3),
                                    ]),
                            ])->visible(fn(Forms\Get $get) => $get('event') !== 'deleted'),
                        ])->maxItems(1)->minItems(1)->columns(1),
                    ]),
                    Forms\Components\Tabs\Tab::make('Actions')->label('Azioni')->schema([
                        Forms\Components\Placeholder::make('placeholder')
                            ->label('Variabili disponibili')->content(function (Forms\Get $get) {
                                //uso la funzione getModelFields per ottenere i campi del modello
                                $modelFields = $get('model_type') ? self::getModelFields(app($get('model_type'))) : [];
                                //devo ritornare come stringa, per ora è un array
                                $modelFieldsString = '';
                                foreach ($modelFields as $key => $value) {
                                    $modelFieldsString .= '<span class="inline-flex items-center rounded-md bg-gray-50 px-2 py-1 text-xs font-medium text-gray-600 ring-1 ring-gray-500/10 ring-inset">{{' . $key . '}}</span> ';
                                }
                                return new HtmlString($modelFieldsString);
                            })->columns(1),
                        Forms\Components\Repeater::make('actions')
                            ->collapsible()
                            ->collapsed()
                            // ->itemLabel(fn(array $state): ?string => $state['action_class'] ? dd($state) : null)
                            ->itemLabel(function (array $state, $component): ?string {
                                if (!$state['action_class']) {
                                    return null;
                                }
                                $key = array_search($state, $component->getState());
                                $index = array_search($key, array_keys($component->getState()));

                                return $state['action_class'] ? '#' . ($index + 1) . ' - ' . self::getActionOptions()[$state['action_class']] : null;
                            })
                            ->schema([
                                Forms\Components\Select::make('action_class')->live()->afterStateUpdated(fn(Forms\Get $get, Forms\Set $set) => $set('action_class', $get('action_class')))->label('Azioni da eseguire')->options(self::getActionOptions())->required(),
                                //dati aggiuntivi per l'azione
                                Forms\Components\Group::make()->schema(function (Forms\Get $get) {
                                    return self::getActionFormByClass($get('action_class'));
                                })->visible(fn($get) => $get('action_class') !== ''),

                                //delay per l'azione numero e unita. es. una input numero e una select unita
                                Forms\Components\Section::make([
                                    Forms\Components\Toggle::make('delay_enabled')->label('Esegui successivamente')->inline(false)->default(false)->live(),
                                    Forms\Components\Group::make()->schema([
                                        Forms\Components\TextInput::make('delay_number')->label('Ritardo')->required()->default(0)->minValue(0),
                                        Forms\Components\Select::make('delay_unit')->label('Unità')->required()
                                            ->options([
                                                'Seconds' => 'Secondi',
                                                'Minutes' => 'Minuti',
                                                'Hours' => 'Ore',
                                                'Days' => 'Giorni',
                                            ])->default('seconds'),
                                    ])->columns(2)->columnSpan(3)->hidden(fn($get) => !$get('delay_enabled')),
                                ])->columns(4),


                            ])->columns(1),
                    ]),
                ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ToggleColumn::make('enabled')->label('Abilitato')->sortable(),
                Tables\Columns\TextColumn::make('title')->label('Titolo')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('description')->label('Descrizione')->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                    $state = $column->getState();
                    if (strlen($state) <= $column->getCharacterLimit()) {
                        return null;
                    }
                    return $state;
                })->limit(25)->searchable(),
                Tables\Columns\TextColumn::make('model_type')->label('Tipo')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('model_type')->label('Tipo')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('trigger')->label('Quando')->getStateUsing(function ($record) {
                    return $record->trigger[0]['event'];
                }),

            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ReplicateAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFilamentAutomations::route('/'),
            'create' => Pages\CreateFilamentAutomation::route('/create'),
            'edit' => Pages\EditFilamentAutomation::route('/{record}/edit'),
        ];
    }
}
