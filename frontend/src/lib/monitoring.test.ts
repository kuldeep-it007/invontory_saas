import { describe, expect, it } from "vitest";
import { formatBytes, formatDuration, levelFor, linePath, niceMax } from "./monitoring";

describe("monitoring helpers", () => {
  it("formats bytes and durations", () => {
    expect(formatBytes(0)).toBe("0 B");
    expect(formatBytes(1536)).toBe("1.5 KB");
    expect(formatBytes(null)).toBe("n/a");
    expect(formatDuration(59)).toBe("59s");
    expect(formatDuration(3725)).toBe("1h 2m");
    expect(formatDuration(90000)).toBe("1d 1h");
  });

  it("classifies values against thresholds in both directions", () => {
    expect(levelFor(50, 80, 95)).toBe("ok");
    expect(levelFor(80, 80, 95)).toBe("warning");
    expect(levelFor(99, 80, 95)).toBe("critical");
    expect(levelFor(null, 80, 95)).toBe("ok");
    expect(levelFor(30, 21, 7, true)).toBe("ok");
    expect(levelFor(10, 21, 7, true)).toBe("warning");
    expect(levelFor(5, 21, 7, true)).toBe("critical");
  });

  it("draws a line and breaks it at missing samples", () => {
    const pts = [
      { ts: 0, v: 0 },
      { ts: 10, v: 100 },
      { ts: 20, v: null },
      { ts: 30, v: 50 },
    ];
    expect(linePath(pts, "v", 300, 100, 100, 0, 30)).toBe("M0.0,100.0 L100.0,0.0 M300.0,50.0");
  });

  it("clamps values above the axis maximum", () => {
    const spike = [{ ts: 0, v: 500 }];
    expect(linePath(spike, "v", 100, 100, 100, 0, 1)).toBe("M0.0,0.0");
  });

  it("picks a round axis maximum", () => {
    expect(niceMax([3, 7])).toBe(10);
    expect(niceMax([120])).toBe(200);
    expect(niceMax([])).toBe(1);
  });
});
