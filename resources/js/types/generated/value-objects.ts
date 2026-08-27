export type Block = {
readonly id: string,
readonly type: string,
readonly content: string,
readonly style: BlockStyle,
readonly transition: Transition | null,
readonly lang: string | null,
readonly src: string | null,
readonly alt: string | null,
};
export type BlockStyle = {
readonly fontSize: string | null,
readonly fontWeight: string | null,
readonly color: string | null,
readonly borderColor: string | null,
readonly backgroundColor: string | null,
readonly gridColumn: string | null,
readonly gridRow: string | null,
};
export type CursorPaginatedDataCollection<TKey, TValue> = CursorPaginator<TKey, TValue>;
export type CursorPaginator<TKey, TValue> = {
data: TKey extends string ? Record<TKey, TValue> : TValue[],
links: {
url: string | null,
label: string,
active: boolean,
}[],
meta: {
path: string,
per_page: number,
next_cursor: string | null,
next_page_url: string | null,
prev_cursor: string | null,
prev_page_url: string | null,
},
};
export type CursorPaginatorInterface<TKey, TValue> = CursorPaginator<TKey, TValue>;
export type LengthAwarePaginator<TKey, TValue> = {
data: TKey extends string ? Record<TKey, TValue> : TValue[],
links: {
url: string | null,
label: string,
active: boolean,
}[],
meta: {
total: number,
current_page: number,
first_page_url: string,
from: number | null,
last_page: number,
last_page_url: string,
next_page_url: string | null,
path: string,
per_page: number,
prev_page_url: string | null,
to: number | null,
},
};
export type LengthAwarePaginatorInterface<TKey, TValue> = LengthAwarePaginator<TKey, TValue>;
export type PaginatedDataCollection<TKey, TValue> = LengthAwarePaginator<TKey, TValue>;
export type PresentationContent = {
readonly version: string,
readonly slides: Slide[],
};
export type Slide = {
readonly id: string,
readonly layout: SlideLayout,
readonly background: string | null,
readonly slots: Record<string, Block[]>,
readonly config: Record<string, any> | null,
};
export type SlideLayout = 'full' | 'center' | 'top-main' | 'top-main-footer' | 'left-right' | 'left-wide-right' | 'grid-2x2' | 'grid-2x3' | 'custom-grid' | 'rich-text';
export type Transition = {
readonly order: number,
};
export type YoYoTranslateInfo = {
readonly session_id: string | null,
readonly websocket_url: string | null,
readonly active: boolean,
readonly started_at: string | null,
};
export type YoYoTranslateSession = {
readonly sessionId: string,
readonly websocketUrl: string,
readonly startedAt: string,
};
