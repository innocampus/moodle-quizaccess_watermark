/**
 * Quiz watermark JS code.
 *
 * @package quizaccess_watermark
 * @copyright 2021 Martin Gauk, TU Berlin <gauk@math.tu-berlin.de>
 */

import $ from 'jquery';

let userUnicodeTagChars = "";
let userZeroWidthChars = "";

export const init = (teacher, hash, backgroundColor, startColor, bitColor) => {
    if (teacher) {
        userUnicodeTagChars += String.fromCodePoint(0xE007A);
        userZeroWidthChars += String.fromCodePoint(0x2064);
    }
    userUnicodeTagChars += getUnicodeWatermarkChars(hash, 16, 0xE0061, 4);
    userZeroWidthChars += getUnicodeWatermarkChars(hash, 8, 0x2060, 2);

    $(".que.shortanswer .answer input, " +
        ".que.calculated .answer input, " +
        ".que.calculatedsimple .answer input, " +
        ".que.numerical .answer input, " +
        ".que.multianswer .subquestion input").each(function(idx, el) {
        new WatermarkInputField(el);
    });

    // Needed for preview attempts.
    $('<input>').attr({
        type: 'hidden',
        name: 'quizaccess_watermark_enable_clean',
        value: '1'
    }).appendTo('#responseform');

    addBackgroundWatermark(hash, backgroundColor, startColor, bitColor);
};

/**
 * This generates a string that starts with zero width chars and ends with the unicode tag chars.
 * Before every space it inserts the zero width chars and after the space it inserts the unicode tag chars.
 *
 * @param str
 * @returns {string}
 */
const getStrWithHiddenChars = function(str) {
    const NEXT_ZW = 0; // zero width char
    const NEXT_TAG = 1; // Unicode tag
    const NEXT_OTHER = 2;

    let nextCharExpected = NEXT_ZW;
    let out = "";

    const iterator = str[Symbol.iterator]();
    let curChar = iterator.next();
    let beforeChar = "";
    while (!curChar.done) {
        if (nextCharExpected === NEXT_ZW) {
            if (!isZeroWidthChar(curChar.value)) {
                out += userZeroWidthChars;
            }
            nextCharExpected = NEXT_OTHER;
        } else if (nextCharExpected === NEXT_TAG) {
            if (!isUnicodeTagChar(curChar.value)) {
                out += userUnicodeTagChars;
            }
            nextCharExpected = NEXT_OTHER;
        } else {
            if (curChar.value === " ") {
                if (!isZeroWidthChar(beforeChar)) {
                    out += userZeroWidthChars;
                }
                nextCharExpected = NEXT_TAG;
            }
            out += curChar.value;
            beforeChar = curChar.value;
            curChar = iterator.next();
        }

    }

    // Ensure that string ends with tag.
    // -2 because we want the code point, not the code unit.
    if (!isUnicodeTagChar(out.codePointAt(out.length - 2))) {
        out += userUnicodeTagChars;
    }

    return out;
};

const isUnicodeTagChar = function(ch) {
    if (typeof ch === 'string' || ch instanceof String) {
        ch = ch.codePointAt(0);
    }
    return 0xE0000 <= ch && ch <= 0xE007F;
};

const isZeroWidthChar = function(ch) {
    if (typeof ch === 'string' || ch instanceof String) {
        ch = ch.codePointAt(0);
    }
    return 0x2060 <= ch && ch <= 0x2064;
};

const getUnicodeWatermarkChars = function(txt, maxLength, baseCodePointInt, bits) {
    let out = "";
    for (let i = 0; i < maxLength; i++) {
        const hex = parseInt(txt.charAt(i), 16);
        if (bits === 4) {
            out += String.fromCodePoint(baseCodePointInt + hex);
        } else if (bits === 2) {
            out += String.fromCodePoint(baseCodePointInt + ((hex >> 2) & 3));
            out += String.fromCodePoint(baseCodePointInt + (hex & 3));
        }
    }
    return out;
};

class WatermarkInputField {
    constructor(field) {
        this.field = field;
        this.dirty = true;
        this.addWatermark();

        $(field).on("input", (evt) => {
            this.dirty = true;
        });
        $(field).keydown((evt) => {
            if (evt.key === "ArrowLeft") {
                evt.preventDefault();
                this.moveSelection("l", evt.shiftKey);
            } else if (evt.key === "ArrowRight") {
                evt.preventDefault();
                this.moveSelection("r", evt.shiftKey);
            } else if (evt.key === "Backspace") {
                this.deleteChar("l");
            } else if (evt.key === "Delete") {
                this.deleteChar("r");
            }
        });
        $(field).blur((evt) => {
            this.addWatermark();
        });
        $(field).select((evt) => {
            // Only modify text when multiple chars are selected.
            if (this.field.selectionStart !== this.field.selectionEnd) {
                this.addWatermark();
            }
        });
    }

    addWatermark() {
        if (this.dirty) {
            this.field.value = getStrWithHiddenChars(this.field.value);
            this.dirty = false;
        }
    }

    moveSelection(direction, shift) {
        const value = this.field.value;
        const start = this.field.selectionStart;
        const end = this.field.selectionEnd;
        const seekStart = (direction === "l") ? start : end;
        const nextPos = this.getPosNextVisibleChar(value, seekStart, direction);
        if (shift && direction === "l") {
            this.field.setSelectionRange(nextPos, end);
        } else if (shift && direction === "r") {
            this.field.setSelectionRange(start, nextPos);
        } else {
            this.field.setSelectionRange(nextPos, nextPos);
        }
    }

    deleteChar(direction) {
        if (this.field.selectionStart === this.field.selectionEnd) {
            // select the characters that will be deleted by the key press
            this.moveSelection(direction, true);
        }
    }

    getPosNextVisibleChar(str, idx, direction) {
        const add = (direction === "r") ? 1 : -1;
        idx += add;
        for (; idx < str.length && idx > 0; idx += add) {
            const char = str.codePointAt(idx);
            // skip low surrogates
            if ((char & 0xFC00) === 0xDC00) {
                continue;
            }
            if (isUnicodeTagChar(char) || isZeroWidthChar(char)) {
                continue;
            }
            return idx;
        }
        return Math.max(0, idx);
    }
}

function* generateBitsOfHash(code) {
    for (let i = 0; i < code.length; i++) {
        let char = parseInt(code[i], 16);
        yield (char >> 3) & 1;
        yield (char >> 2) & 1;
        yield (char >> 1) & 1;
        yield char & 1;
    }
    while (true) {
        yield 0;
    }
}

function addBackgroundWatermark(hash, backgroundColor, startColor, bitColor) {
    let pix = generateBitsOfHash(hash);
    let svg = `<svg version="1.1" viewBox="0 0 90 90" xmlns="http://www.w3.org/2000/svg">
<rect x="0" y="0" width="90" height="90" fill="${backgroundColor}"/>
<rect x="1" y="10" width="1" height="1" fill="${startColor}"/>`;

    for (let y=10; y<90; y+=10) {
        for (let x=10; x<90; x+=10) {
            if (pix.next().value > 0) {
                svg += `<rect x="${x}" y="${y}" width="1" height="1" fill="${bitColor}"/>`;
            }
        }
    }
    svg += '</svg>';

    const rule = `<style>.que .formulation {
        background-repeat: repeat;
        background-size: 90px 90px;
        background-image: url(data:image/svg+xml;base64,${btoa(svg)});
    }</style>`;
    $(rule).appendTo("head");
}
