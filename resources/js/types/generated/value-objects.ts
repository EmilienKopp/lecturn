export type Block = {
    readonly id: string;
    readonly type: string;
    readonly content: string;
    readonly style: BlockStyle;
    readonly transition: Transition | null;
    readonly lang: string | null;
    readonly src: string | null;
    readonly alt: string | null;
    readonly actions: CodeAction[];
};
export type BlockStyle = {
    readonly fontSize: string | null;
    readonly fontWeight: string | null;
    readonly color: string | null;
    readonly borderColor: string | null;
    readonly backgroundColor: string | null;
    readonly gridColumn: string | null;
    readonly gridRow: string | null;
    readonly x: string | null;
    readonly y: string | null;
    readonly width: string | null;
    readonly height: string | null;
};
export type CodeAction = {
    readonly id: string;
    readonly code: string;
    readonly highlightLines: string | null;
    readonly label: string | null;
};
export type CursorPaginatedDataCollection<TKey, TValue> = CursorPaginator<
    TKey,
    TValue
>;
export type CursorPaginator<TKey, TValue> = {
    data: TKey extends string ? Record<TKey, TValue> : TValue[];
    links: {
        url: string | null;
        label: string;
        active: boolean;
    }[];
    meta: {
        path: string;
        per_page: number;
        next_cursor: string | null;
        next_page_url: string | null;
        prev_cursor: string | null;
        prev_page_url: string | null;
    };
};
export type CursorPaginatorInterface<TKey, TValue> = CursorPaginator<
    TKey,
    TValue
>;
export type FlowEdge = {
    readonly id: string;
    readonly source: string;
    readonly target: string;
    readonly label: string | null;
};
export type FlowGraph = {
    readonly version: string;
    readonly nodes: FlowNode[];
    readonly edges: FlowEdge[];
};
export type FlowNode = {
    readonly id: string;
    readonly type: FlowNodeType;
    readonly position: NodePosition;
    readonly data: {
        slideId?: string;
        label?: string | null;
        blockId?: string;
        actionId?: string;
    };
};
export type FlowNodeType = 'slide' | 'transition' | 'code-action';
export type LengthAwarePaginator<TKey, TValue> = {
    data: TKey extends string ? Record<TKey, TValue> : TValue[];
    links: {
        url: string | null;
        label: string;
        active: boolean;
    }[];
    meta: {
        total: number;
        current_page: number;
        first_page_url: string;
        from: number | null;
        last_page: number;
        last_page_url: string;
        next_page_url: string | null;
        path: string;
        per_page: number;
        prev_page_url: string | null;
        to: number | null;
    };
};
export type LengthAwarePaginatorInterface<TKey, TValue> = LengthAwarePaginator<
    TKey,
    TValue
>;
export type NodePosition = {
    readonly x: number;
    readonly y: number;
};
export type PaginatedDataCollection<TKey, TValue> = LengthAwarePaginator<
    TKey,
    TValue
>;
export type PresentationContent = {
    readonly version: string;
    readonly slides: Slide[];
    readonly backgroundImage: string | null;
};
export type Slide = {
    readonly id: string;
    readonly layout: SlideLayout;
    readonly background: string | null;
    readonly slots: Record<string, Block[]>;
    readonly config: Record<string, any> | null;
    readonly title: string | null;
};
export type SlideLayout =
    | 'full'
    | 'center'
    | 'top-main'
    | 'top-main-footer'
    | 'left-right'
    | 'left-wide-right'
    | 'grid-2x2'
    | 'grid-2x3'
    | 'custom-grid'
    | 'rich-text'
    | 'free';
export type TalkSettings = {
    readonly showReactions: boolean;
    readonly showDock: boolean;
    readonly showTranslation: boolean;
    readonly timerMode: string;
    readonly durationMinutes: number | null;
};
export type Transition = {
    readonly nodeId: string | null;
    readonly order: number | null;
};
export type YoYoTranslateInfo = {
    readonly session_id: string | null;
    readonly websocket_url: string | null;
    readonly active: boolean;
    readonly started_at: string | null;
};
export type YoYoTranslateSession = {
    readonly websocketUrl: string;
    readonly sessionId: string;
    readonly startedAt: undefined;
};
