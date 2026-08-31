interface TimeBreakdown {
    seconds: () => number;
    minutes: () => number;
    hours: () => number;
    days: () => number;
    milliseconds: () => number;
}

export function ms(milliseconds: number): TimeBreakdown {
    return {
        seconds: () => milliseconds / 1000,
        minutes: () => milliseconds / 1000 / 60,
        hours: () => milliseconds / 1000 / 60 / 60,
        days: () => milliseconds / 1000 / 60 / 60 / 24,
        milliseconds: () => milliseconds,
    };
}

export function s(seconds: number): TimeBreakdown {
    return ms(seconds * 1000);
}

export function min(minutes: number): TimeBreakdown {
    return ms(minutes * 60 * 1000);
}

export function h(hours: number): TimeBreakdown {
    return ms(hours * 60 * 60 * 1000);
}

export function d(days: number): TimeBreakdown {
    return ms(days * 24 * 60 * 60 * 1000);
}

export const Time = {
    ms,
    s,
    min,
    h,
    d,
};
