/**
 * Главное меню приложения.
 *
 * Открывается «гамбургером» в правом углу шапки экрана (см. `app/index.tsx`).
 * Здесь живут действия уровня аккаунта — сейчас это выход; email пользователя
 * показывается подписью в шапке меню, чтобы данные аккаунта не занимали место
 * на самой доске.
 *
 * Выход подтверждается прямо в меню (пункт списка заменяется блоком «Выйти из
 * аккаунта? Да/Отмена»): системные диалоги на web не работают, а поведение
 * должно быть одинаковым на всех платформах — так же устроено удаление
 * направления в `DirectionsModal`.
 *
 * Реализовано модалкой-оверлеем, а не absolute-дропдауном: так оно одинаково
 * ведёт себя на native и на web и не зависит от размеров хедера (в т.ч. на web
 * заголовок рисуется иначе). Раскладка повторяет меню настроек уровня
 * (`LevelSettingsMenu`).
 */

import { useState } from 'react';
import { Modal, Pressable, Text, View } from 'react-native';

/**
 * Действие меню.
 *
 * Описано объединением, а не полем-флагом: пункт либо просто вызывает `onPress`
 * (открывает попап), либо сначала просит подтверждение и только потом вызывает
 * `onPress` — меню само решает, как показать подтверждение.
 */
export type MainMenuItem =
  | ConfirmedMainMenuItem
  | {
      label: string;
      onPress: () => void;
      confirm?: false;
    };

/** Пункт с подтверждением — единственный вариант, у которого есть текст вопроса. */
interface ConfirmedMainMenuItem {
  label: string;
  onPress: () => void;
  /** true — перед выполнением спросить подтверждение. */
  confirm: true;
  /** Текст подтверждения, например «Выйти из аккаунта?». */
  confirmQuestion: string;
}

/** Пункт ждёт подтверждения именно в этом виде: у него есть `confirmQuestion`. */
function isConfirmedItem(item: MainMenuItem): item is ConfirmedMainMenuItem {
  return item.confirm === true;
}

interface MainMenuProps {
  /** true — меню открыто. */
  visible: boolean;
  /** Email текущего пользователя — подпись в шапке меню. */
  email: string;
  items: readonly MainMenuItem[];
  onClose: () => void;
}

export function MainMenu({ visible, email, items, onClose }: MainMenuProps) {
  /** Пункт, ожидающий подтверждения; null — ни один не ждёт. */
  const [pending, setPending] = useState<ConfirmedMainMenuItem | null>(null);

  /** Закрыть меню и сбросить незавершённое подтверждение. */
  const close = () => {
    setPending(null);
    onClose();
  };

  /** Клик по пункту: у действий с подтверждением сначала показываем вопрос. */
  const handleItemPress = (item: MainMenuItem) => {
    if (isConfirmedItem(item)) {
      setPending(item);

      return;
    }

    setPending(null);
    item.onPress();
  };

  /** Подтверждение получено — выполняем действие и гасим меню. */
  const confirmPending = () => {
    if (pending === null) {
      return;
    }

    const item = pending;
    setPending(null);
    item.onPress();
  };

  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={close}>
      {/* Затемнение — отдельный слой, а не обёртка вокруг меню: на web Pressable
          рендерится в <button>, и вложенные кнопки недопустимы. */}
      <View className="flex-1 items-center justify-center px-4">
        <Pressable
          accessibilityRole="button"
          accessibilityLabel="Закрыть меню"
          onPress={close}
          className="absolute inset-0 bg-black/40"
        />

        <View className="w-full max-w-sm gap-3 rounded-2xl bg-card p-4">
          <View className="gap-0.5">
            <Text className="text-base font-bold text-app">Меню</Text>
            <Text className="text-xs text-muted" numberOfLines={1}>
              {email}
            </Text>
          </View>

          {/* Ожидание подтверждения заменяет список: пока пользователь не ответил,
              другие пункты меню недоступны. */}
          {pending !== null ? (
            <View className="gap-2 rounded-lg bg-err-bg p-2.5">
              <Text className="text-xs font-semibold text-err-fg">{pending.confirmQuestion}</Text>

              <View className="flex-row gap-2">
                <Pressable
                  accessibilityRole="button"
                  onPress={() => setPending(null)}
                  className="h-9 flex-1 items-center justify-center rounded-lg bg-card active:opacity-80"
                >
                  <Text className="text-xs font-semibold text-app">Отмена</Text>
                </Pressable>

                <Pressable
                  accessibilityRole="button"
                  onPress={confirmPending}
                  className="h-9 flex-1 items-center justify-center rounded-lg bg-err-fg active:opacity-90"
                >
                  <Text className="text-xs font-semibold text-white">Выйти</Text>
                </Pressable>
              </View>
            </View>
          ) : items.length === 0 ? (
            <Text className="text-xs text-subtle">Пунктов пока нет.</Text>
          ) : (
            <View className="overflow-hidden rounded-xl border border-border">
              {items.map((item, index) => (
                <Pressable
                  key={item.label}
                  accessibilityRole="button"
                  onPress={() => handleItemPress(item)}
                  className={['px-3 py-3 active:bg-surface', index > 0 ? 'border-t border-border' : ''].join(' ')}
                >
                  <Text className="text-sm text-app">{item.label}</Text>
                </Pressable>
              ))}
            </View>
          )}

          <Pressable
            accessibilityRole="button"
            onPress={close}
            className="h-10 items-center justify-center rounded-xl bg-surface active:opacity-80"
          >
            <Text className="text-sm font-semibold text-app">Закрыть</Text>
          </Pressable>
        </View>
      </View>
    </Modal>
  );
}
