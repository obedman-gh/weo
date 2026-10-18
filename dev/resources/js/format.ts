export const money = (value: string | number | undefined) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));
export const label = (value?: string) => (value ?? "").replaceAll("_", " ");
export const date = (value?: string, zone = "Asia/Makassar", time = false) =>
    value
        ? new Intl.DateTimeFormat("id-ID", {
              timeZone: zone,
              day: "numeric",
              month: "short",
              year: "numeric",
              ...(time ? { hour: "2-digit", minute: "2-digit" } : {}),
          }).format(
              new Date(value.length === 10 ? `${value}T00:00:00Z` : value),
          )
        : "—";
export const localInput = (value?: string, zone = "Asia/Makassar") => {
    if (!value) return "";
    const parts = new Intl.DateTimeFormat("sv-SE", {
        timeZone: zone,
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
        hourCycle: "h23",
    }).format(new Date(value));
    return parts.replace(" ", "T");
};
export const daysUntil = (value: string, zone: string) => {
    const today = new Intl.DateTimeFormat("sv-SE", { timeZone: zone }).format(
        new Date(),
    );
    return Math.ceil(
        (Date.parse(value.slice(0, 10)) - Date.parse(today)) / 86400000,
    );
};
