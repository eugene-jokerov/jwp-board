/**
 * Модалка редактирования карточки.
 *
 * Открывается кликом по карточке. Пока редактируются название и статус
 * (колонка доски). Правило смены статуса то же, что у стрелок на карточке:
 * если у карточки есть дети в «В работе», смена статуса запрещена — статусы
 * в списке заблокированы, причина показана под ними.
 */

import { useState } from 'react';
import { Modal, Pressable, Text, View } from 'react-native';

import { FormAlert } from '../../components/FormAlert';
import { TextField } from '../../components/TextField';
import { EMPTY_COLOR } from '../colors';
import { COLUMNS, KIND_LABEL, type Card, type ColumnId } from '../types';

interface CardEditModalProps {
  /** Редактируемая карточка; null — модалка закрыта. */
  card: Card | null;
  /** Причина, по которой сменить статус нельзя; null — смена разрешена. */
  lockedReason: string | null;
  /** Сохранить изменения; возвращает текст ошибки, который показываем в модалке. */
  onSave: (card: Card, title: string, column: ColumnId) => string | null;
  onClose: () => void;
}

/**
 * Модалка с формой редактирования.
 *
 * Форма инициализируется данными карточки при монтировании, а экран монтирует
 * её заново на каждое открытие (`key` по id карточки) — эффект синхронизации
 * состояния не нужен.
 */
export function CardEditModal(props: CardEditModalProps) {
  if (props.card === null) {
    return null;
  }

  return (
    <CardEditForm
      key={props.card.id}
      card={props.card}
      lockedReason={props.lockedReason}
      onSave={props.onSave}
      onClose={props.onClose}
    />
  );
}

interface CardEditFormProps {
  card: Card;
  lockedReason: string | null;
  onSave: (card: Card, title: string, column: ColumnId) => string | null;
  onClose: () => void;
}

interface StatusOptionProps {
  label: string;
  selected: boolean;
  locked: boolean;
  onPress: () => void;
}

function StatusOption({ label, selected, locked, onPress }: StatusOptionProps) {
  return (
    <Pressable
      accessibilityRole="radio"
      accessibilityState={{ selected, disabled: locked }}
      disabled={locked}
      onPress={onPress}
      className={[
        'flex-row items-center gap-2 rounded-xl border px-3 py-2',
        selected ? 'border-app bg-surface' : 'border-border',
        locked ? 'opacity-50' : '',
      ].join(' ')}
    >
      <Text className="flex-1 text-sm text-app">{label}</Text>

      {selected ? <Text className="text-xs font-semibold text-app">✓</Text> : null}
    </Pressable>
  );
}

function CardEditForm({ card, lockedReason, onSave, onClose }: CardEditFormProps) {
  const [title, setTitle] = useState(card.title);
  const [columnId, setColumnId] = useState<ColumnId>(card.column);
  const [titleError, setTitleError] = useState<string | null>(null);
  // Ошибки сохранения (например, смену статуса запретили данные) показываем здесь же.
  const [formError, setFormError] = useState<string | null>(null);

  const submit = () => {
    const trimmed = title.trim();

    if (trimmed === '') {
      setTitleError('Укажите название.');
      return;
    }

    const error = onSave(card, trimmed, columnId);

    if (error !== null) {
      setFormError(error);
    }
  };

  const titleEmpty = title.trim() === '';

  return (
    <Modal visible transparent animationType="fade" onRequestClose={onClose}>
      <View className="flex-1 items-center justify-center bg-black/40 px-4">
        <View className="w-full max-w-md gap-4 rounded-2xl bg-card p-5">
          <View className="gap-1">
            <Text className="text-lg font-bold text-app">Карточка</Text>
            <Text className="text-xs text-muted">{KIND_LABEL[card.kind]}</Text>
          </View>

          {formError ? <FormAlert message={formError} /> : null}

          <TextField
            label="Название"
            value={title}
            onChangeText={(value) => {
              setTitle(value);
              setTitleError(null);
              setFormError(null);
            }}
            error={titleError}
            placeholder="Например: Запуск новой ветки"
          />

          <View className="gap-1.5">
            <Text className="text-sm font-medium text-app">Статус</Text>

            <View className="gap-1.5">
              {COLUMNS.map((option) => (
                <StatusOption
                  key={option.id}
                  label={option.label}
                  selected={option.id === columnId}
                  // При блокировке доступна только текущая колонка: статус не меняется.
                  locked={lockedReason !== null && option.id !== card.column}
                  onPress={() => {
                    setColumnId(option.id);
                    setFormError(null);
                  }}
                />
              ))}
            </View>

            {lockedReason ? <Text className="text-xs text-err-fg">{lockedReason}</Text> : null}
          </View>

          <View className="flex-row items-center gap-2">
            <Text className="text-xs text-subtle">Цвет карточки</Text>

            <View
              className="h-4 w-4 rounded-full border border-border"
              style={{ backgroundColor: card.color ?? EMPTY_COLOR }}
            />
          </View>

          <View className="flex-row gap-2">
            <Pressable
              accessibilityRole="button"
              onPress={onClose}
              className="h-11 flex-1 items-center justify-center rounded-xl bg-surface active:opacity-80"
            >
              <Text className="text-sm font-semibold text-app">Отмена</Text>
            </Pressable>

            <Pressable
              accessibilityRole="button"
              accessibilityState={{ disabled: titleEmpty }}
              disabled={titleEmpty}
              onPress={submit}
              className={[
                'h-11 flex-1 items-center justify-center rounded-xl bg-app active:opacity-90',
                titleEmpty ? 'opacity-60' : '',
              ].join(' ')}
            >
              <Text className="text-sm font-semibold text-white">Сохранить</Text>
            </Pressable>
          </View>
        </View>
      </View>
    </Modal>
  );
}
