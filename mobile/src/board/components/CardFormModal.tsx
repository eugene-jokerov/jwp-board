/**
 * Модалка создания карточки.
 *
 * Два режима:
 * - «с родителем из свимлайна» — родитель предопределён, цвет уже известен;
 * - «с выбором родителя» — карточку уровня создают кнопкой в шапке уровня,
 *   родитель обязателен (для тактики/операционного — только из «В работе»),
 *   у операционного уровня есть опция «Без проекта» (карточка получает служебный цвет c0).
 */

import { useState } from 'react';
import { Modal, Pressable, ScrollView, Text, View } from 'react-native';

import { TextField } from '../../components/TextField';
import { EMPTY_COLOR } from '../colors';
import { UNPARENTED_CARD_COLOR, directionColorHex } from '../directionColors';
import type { CardKind, ColumnId, ParentOptionModel } from '../types';
import { KIND_LABEL, PARENT_LABEL } from '../types';

export interface CardDraft {
  kind: CardKind;
  /** Свимлайн, из которого открыли форму: там родитель предопределён. */
  laneId: string | null;
  /** Цвет, который унаследует карточка из свимлайна (null — без цвета). */
  laneColor: string | null;
  /** Название свимлайна — показываем в шапке формы. */
  laneTitle: string | null;
  /**
   * Допустимые родители: карточки в работе, а для инициативы — направления.
   * null — выбор не нужен.
   */
  parentOptions: ParentOptionModel[] | null;
  /** Можно ли создать карточку без родителя (операционный уровень). */
  allowNoParent: boolean;
  columnLabel: string;
  /** Колонка, в которую попадёт карточка. */
  columnId: ColumnId;
  /** Название уровня — для шапки формы. */
  levelLabel: string;
}

interface CardFormModalProps {
  draft: CardDraft | null;
  onCreate: (draft: CardDraft, title: string, parentId: string | null) => void;
  onClose: () => void;
}

interface ParentOptionProps {
  label: string;
  color: string | null;
  selected: boolean;
  onPress: () => void;
}

function ParentOption({ label, color, selected, onPress }: ParentOptionProps) {
  return (
    <Pressable
      accessibilityRole="radio"
      accessibilityState={{ selected }}
      onPress={onPress}
      className={[
        'flex-row items-center gap-2 rounded-xl border px-3 py-2',
        selected ? 'border-app bg-surface' : 'border-border',
      ].join(' ')}
    >
      <View
        className="h-3 w-3 rounded-full border border-border"
        style={{ backgroundColor: directionColorHex(color) ?? EMPTY_COLOR }}
      />

      <Text className="flex-1 text-sm text-app" numberOfLines={1}>
        {label}
      </Text>

      {selected ? <Text className="text-xs font-semibold text-app">✓</Text> : null}
    </Pressable>
  );
}

function parentErrorText(kind: CardKind): string {
  return kind === 'initiative' ? 'Выберите направление.' : 'Выберите родителя (только карточки в колонке «В работе»).';
}

/** Пояснение под пустым списком родителей: у направлений оно не нужно. */
function parentEmptyHint(kind: CardKind): string | null {
  return kind === 'initiative' ? null : 'Нет карточек в колонке «В работе» — выбрать нечего.';
}

export function CardFormModal({ draft, onCreate, onClose }: CardFormModalProps) {
  const [title, setTitle] = useState('');
  const [parentId, setParentId] = useState<string | null>(null);
  const [titleError, setTitleError] = useState<string | null>(null);
  const [parentError, setParentError] = useState<string | null>(null);

  // Состояние формы сбрасываем при закрытии, а не эффектом при открытии.
  const close = () => {
    setTitle('');
    setParentId(null);
    setTitleError(null);
    setParentError(null);
    onClose();
  };

  if (draft === null) {
    return null;
  }

  const parentOptions = draft.parentOptions;
  const parentRequired = parentOptions !== null && !draft.allowNoParent;
  const selectedParent = parentOptions?.find((card) => card.id === parentId) ?? null;
  const previewColor = draft.laneColor ?? selectedParent?.color ?? null;

  // Безымянную карточку не создаём: кнопка ждёт название, а родителя —
  // только если выбор обязателен (направление для инициативы, инициатива для проекта).
  const createDisabled = title.trim() === '' || (parentRequired && selectedParent === null);

  const submit = () => {
    const titleValue = title.trim();
    const parentInvalid = parentRequired && selectedParent === null;

    if (titleValue === '') {
      setTitleError('Укажите название.');
    }

    if (parentInvalid) {
      setParentError(parentErrorText(draft.kind));
    }

    if (titleValue === '' || parentInvalid) {
      return;
    }

    onCreate(draft, titleValue, draft.laneId ?? parentId);
    setTitle('');
    setParentId(null);
    setTitleError(null);
    setParentError(null);
  };

  return (
    <Modal visible transparent animationType="fade" onRequestClose={close}>
      <View className="flex-1 items-center justify-center bg-black/40 px-4">
        <View className="w-full max-w-md gap-4 rounded-2xl bg-card p-5">
          <View className="gap-1">
            <Text className="text-lg font-bold text-app">Новый {KIND_LABEL[draft.kind].toLowerCase()}</Text>
            <Text className="text-xs text-muted">
              {draft.levelLabel} · {draft.columnLabel}
              {draft.laneTitle ? ` · ${draft.laneTitle}` : ''}
            </Text>
          </View>

          <TextField
            label="Название"
            value={title}
            onChangeText={(value) => {
              setTitle(value);
              setTitleError(null);
            }}
            error={titleError}
            placeholder="Например: Запуск новой ветки"
            autoFocus
          />

          {parentOptions !== null ? (
            <View className="gap-1.5">
              <Text className="text-sm font-medium text-app">{PARENT_LABEL[draft.kind]}</Text>

              <ScrollView className="max-h-48" contentContainerClassName="gap-1.5">
                {draft.allowNoParent ? (
                  <ParentOption
                    label="Без проекта (нейтральный цвет)"
                    color={UNPARENTED_CARD_COLOR}
                    selected={parentId === null}
                    onPress={() => {
                      setParentId(null);
                      setParentError(null);
                    }}
                  />
                ) : null}

                {parentOptions.map((option) => (
                  <ParentOption
                    key={option.id}
                    label={option.title}
                    color={option.color}
                    selected={parentId === option.id}
                    onPress={() => {
                      setParentId(option.id);
                      setParentError(null);
                    }}
                  />
                ))}
              </ScrollView>

              {parentOptions.length === 0 && !draft.allowNoParent ? (
                <Text className="text-xs text-err-fg">{parentEmptyHint(draft.kind) ?? 'Выбрать нечего.'}</Text>
              ) : null}

              {parentError ? <Text className="text-xs text-err-fg">{parentError}</Text> : null}
            </View>
          ) : null}

          <View className="flex-row items-center gap-2">
            <Text className="text-xs text-subtle">Цвет карточки</Text>

            <View
              className="h-4 w-4 rounded-full border border-border"
              style={{ backgroundColor: directionColorHex(previewColor) ?? EMPTY_COLOR }}
            />

            <Text className="text-xs text-muted">{previewColor === null ? 'без цвета' : 'наследуется'}</Text>
          </View>

          <View className="flex-row gap-2">
            <Pressable
              accessibilityRole="button"
              onPress={close}
              className="h-11 flex-1 items-center justify-center rounded-xl bg-surface active:opacity-80"
            >
              <Text className="text-sm font-semibold text-app">Отмена</Text>
            </Pressable>

            <Pressable
              accessibilityRole="button"
              accessibilityState={{ disabled: createDisabled }}
              disabled={createDisabled}
              onPress={submit}
              className={[
                'h-11 flex-1 items-center justify-center rounded-xl bg-app active:opacity-90',
                createDisabled ? 'opacity-60' : '',
              ].join(' ')}
            >
              <Text className="text-sm font-semibold text-white">Создать</Text>
            </Pressable>
          </View>
        </View>
      </View>
    </Modal>
  );
}
