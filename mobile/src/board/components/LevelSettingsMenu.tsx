/**
 * Выпадающее меню «Настройки уровня».
 *
 * Открывается иконкой в строке с кнопкой создания карточки. Набор пунктов
 * задаёт экран: у каждого уровня будут свои настройки, поэтому меню принимает
 * готовый список (сейчас на стратегическом только «Направления»).
 *
 * Реализовано модалкой-оверлеем, а не absolute-дропдауном: так оно одинаково
 * ведёт себя на native и на web и не обрезается контейнером уровня.
 */

import { Modal, Pressable, Text, View } from 'react-native';

import { TOAST_MODAL_STYLE } from '../../notifications/NotificationHost';

export interface LevelMenuItem {
  label: string;
  onPress: () => void;
}

interface LevelSettingsMenuProps {
  /** true — меню открыто. */
  visible: boolean;
  /** Название уровня — подпись в шапке меню. */
  levelLabel: string;
  items: readonly LevelMenuItem[];
  onClose: () => void;
}

export function LevelSettingsMenu({ visible, levelLabel, items, onClose }: LevelSettingsMenuProps) {
  return (
    // `style` — чтобы тосты не оказались под меню (см. TOAST_MODAL_STYLE
    // в notifications/NotificationHost).
    <Modal visible={visible} transparent animationType="fade" onRequestClose={onClose} style={TOAST_MODAL_STYLE}>
      {/* Затемнение — отдельный слой, а не обёртка вокруг меню: на web Pressable
          рендерится в <button>, и вложенные кнопки недопустимы. */}
      <View className="flex-1 items-center justify-center px-4">
        <Pressable
          accessibilityRole="button"
          accessibilityLabel="Закрыть меню"
          onPress={onClose}
          className="absolute inset-0 bg-black/40"
        />

        <View className="w-full max-w-sm gap-3 rounded-2xl bg-card p-4">
          <View className="gap-0.5">
            <Text className="text-base font-bold text-app">Настройки уровня</Text>
            <Text className="text-xs text-muted" numberOfLines={1}>
              {levelLabel}
            </Text>
          </View>

          {items.length === 0 ? (
            <Text className="text-xs text-subtle">Для этого уровня настроек пока нет.</Text>
          ) : (
            <View className="overflow-hidden rounded-xl border border-border">
              {items.map((item, index) => (
                <Pressable
                  key={item.label}
                  accessibilityRole="button"
                  onPress={item.onPress}
                  className={['px-3 py-3 active:bg-surface', index > 0 ? 'border-t border-border' : ''].join(' ')}
                >
                  <Text className="text-sm text-app">{item.label}</Text>
                </Pressable>
              ))}
            </View>
          )}

          <Pressable
            accessibilityRole="button"
            onPress={onClose}
            className="h-10 items-center justify-center rounded-xl bg-surface active:opacity-80"
          >
            <Text className="text-sm font-semibold text-app">Закрыть</Text>
          </Pressable>
        </View>
      </View>
    </Modal>
  );
}
