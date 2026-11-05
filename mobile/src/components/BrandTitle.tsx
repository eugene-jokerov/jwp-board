/**
 * Лого и слоган в заголовке навигации: «JWP Board» и под ним «Персональный канбан».
 *
 * В `title` заголовка стека умещается только одна строка, поэтому слоган
 * выводится как нижняя часть той же строки: компонент передаётся в `headerTitle`
 * (см. `app/_layout.tsx`) и растягивается на центр заголовка.
 *
 * Центр заголовка по умолчанию ограничен по высоте высотой хедера — слоган не
 * помещается и был бы обрезан. Поэтому блок смещён вниз на `overflow`: он
 * продолжается под хедер, а родительский `pointerEvents="box-none"` не мешает
 * касаниям (слоган не интерактивен).
 */

import { Text, View } from 'react-native';

/** Название продукта — лого. */
export const BRAND_NAME = 'JWP Board';

/** Слоган под названием. */
export const BRAND_TAGLINE = 'Персональный канбан';

interface BrandTitleProps {
  /** Отступ блока вниз, px: на эту же величину слоган выходит под заголовок. */
  overflow: number;
  /** Цвет названия — тот же, что у обычного заголовка стека. */
  color: string;
  /** Размер шрифта названия; слоган на 6 px меньше. */
  fontSize: number;
  fontFamily?: string;
  fontWeight?: '400' | '500' | '600' | '700';
}

export function BrandTitle({ overflow, color, fontSize, fontFamily, fontWeight }: BrandTitleProps) {
  return (
    <View
      className="items-center justify-center"
      style={{ paddingBottom: overflow, transform: [{ translateY: overflow / 2 }] }}
    >
      <Text numberOfLines={1} style={{ color, fontSize, fontFamily, fontWeight }} className="text-center">
        {BRAND_NAME}
      </Text>

      <Text
        numberOfLines={1}
        className="text-center text-subtle"
        style={{ fontSize: fontSize - 6, fontFamily, fontWeight: '500' }}
      >
        {BRAND_TAGLINE}
      </Text>
    </View>
  );
}
