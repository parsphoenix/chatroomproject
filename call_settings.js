/**
 * تنظیمات تماس صوتی و تصویری
 */

// تنظیمات پیش‌فرض
const CALL_SETTINGS = {
    // کیفیت تصویر پیش‌فرض
    defaultVideoQuality: '360p',
    
    // حداکثر پهنای باند (kbps)
    maxBitrate: 1600,
    
    // تنظیمات صدا
    audio: {
        echoCancellation: true,
        noiseSuppression: true,
        autoGainControl: true,
        sampleRate: 48000
    },
    
    // تنظیمات تصویر
    video: {
        '240p': { width: 320, height: 240, frameRate: 15 },
        '360p': { width: 480, height: 360, frameRate: 20 },
        '480p': { width: 640, height: 480, frameRate: 25 },
        '720p': { width: 1280, height: 720, frameRate: 30 } // برای آینده
    },
    
    // STUN servers
    iceServers: [
        { urls: 'stun:stun.l.google.com:19302' },
        { urls: 'stun:stun1.l.google.com:19302' },
        { urls: 'stun:stun2.l.google.com:19302' },
        { urls: 'stun:stun3.l.google.com:19302' },
        { urls: 'stun:stun4.l.google.com:19302' }
    ],
    
    // تنظیمات UI
    ui: {
        // زمان نمایش notification (میلی‌ثانیه)
        notificationDuration: 5000,
        
        // زمان انتظار برای پاسخ تماس (ثانیه)
        callTimeout: 60,
        
        // فعال/غیرفعال کردن صدای زنگ
        enableRingtone: true,
        
        // فعال/غیرفعال کردن ارتعاش
        enableVibration: true,
        
        // نمایش خودکار چت در حین تماس
        autoShowCallChat: false
    },
    
    // تنظیمات ضبط
    recording: {
        // فرمت ضبط
        mimeType: 'audio/webm',
        
        // کیفیت ضبط
        audioBitsPerSecond: 128000
    },
    
    // تنظیمات اشتراک صفحه
    screenShare: {
        // شامل صدای سیستم
        includeSystemAudio: true,
        
        // کیفیت اشتراک صفحه
        video: {
            width: 1920,
            height: 1080,
            frameRate: 15
        }
    }
};

// تابع دریافت تنظیمات از localStorage
function getCallSettings() {
    const saved = localStorage.getItem('call_settings');
    if (saved) {
        try {
            const parsed = JSON.parse(saved);
            return { ...CALL_SETTINGS, ...parsed };
        } catch (error) {
            console.error('خطا در خواندن تنظیمات:', error);
        }
    }
    return CALL_SETTINGS;
}

// تابع ذخیره تنظیمات در localStorage
function saveCallSettings(settings) {
    try {
        localStorage.setItem('call_settings', JSON.stringify(settings));
        return true;
    } catch (error) {
        console.error('خطا در ذخیره تنظیمات:', error);
        return false;
    }
}

// تابع ریست تنظیمات به حالت پیش‌فرض
function resetCallSettings() {
    localStorage.removeItem('call_settings');
    return CALL_SETTINGS;
}

// تابع بروزرسانی تنظیمات خاص
function updateCallSetting(key, value) {
    const settings = getCallSettings();
    
    // پشتیبانی از nested keys مثل 'ui.enableRingtone'
    const keys = key.split('.');
    let current = settings;
    
    for (let i = 0; i < keys.length - 1; i++) {
        if (!current[keys[i]]) {
            current[keys[i]] = {};
        }
        current = current[keys[i]];
    }
    
    current[keys[keys.length - 1]] = value;
    
    return saveCallSettings(settings);
}

// تابع دریافت تنظیم خاص
function getCallSetting(key, defaultValue = null) {
    const settings = getCallSettings();
    
    const keys = key.split('.');
    let current = settings;
    
    for (const k of keys) {
        if (current && typeof current === 'object' && k in current) {
            current = current[k];
        } else {
            return defaultValue;
        }
    }
    
    return current;
}

// Export برای استفاده در سایر فایل‌ها
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        CALL_SETTINGS,
        getCallSettings,
        saveCallSettings,
        resetCallSettings,
        updateCallSetting,
        getCallSetting
    };
}

// تنظیمات پیش‌فرض برای مرورگرهای مختلف
const BROWSER_SPECIFIC_SETTINGS = {
    // Safari
    safari: {
        video: {
            '240p': { width: 320, height: 240, frameRate: 12 },
            '360p': { width: 480, height: 360, frameRate: 15 },
            '480p': { width: 640, height: 480, frameRate: 20 }
        }
    },
    
    // Firefox
    firefox: {
        audio: {
            echoCancellation: true,
            noiseSuppression: false, // مشکل در برخی نسخه‌ها
            autoGainControl: true
        }
    },
    
    // Chrome Mobile
    chromeMobile: {
        maxBitrate: 800, // محدودیت بیشتر برای موبایل
        video: {
            '240p': { width: 320, height: 240, frameRate: 15 },
            '360p': { width: 480, height: 360, frameRate: 15 },
            '480p': { width: 640, height: 480, frameRate: 20 }
        }
    }
};

// تشخیص مرورگر و اعمال تنظیمات مخصوص
function getBrowserSpecificSettings() {
    const userAgent = navigator.userAgent.toLowerCase();
    
    if (userAgent.includes('safari') && !userAgent.includes('chrome')) {
        return BROWSER_SPECIFIC_SETTINGS.safari;
    } else if (userAgent.includes('firefox')) {
        return BROWSER_SPECIFIC_SETTINGS.firefox;
    } else if (userAgent.includes('chrome') && /android|iphone|ipad|ipod|blackberry|iemobile|opera mini/i.test(userAgent)) {
        return BROWSER_SPECIFIC_SETTINGS.chromeMobile;
    }
    
    return {};
}

// ترکیب تنظیمات پیش‌فرض با تنظیمات مخصوص مرورگر
function getOptimizedCallSettings() {
    const baseSettings = getCallSettings();
    const browserSettings = getBrowserSpecificSettings();
    
    return mergeDeep(baseSettings, browserSettings);
}

// تابع کمکی برای ترکیب عمیق objects
function mergeDeep(target, source) {
    const output = Object.assign({}, target);
    
    if (isObject(target) && isObject(source)) {
        Object.keys(source).forEach(key => {
            if (isObject(source[key])) {
                if (!(key in target)) {
                    Object.assign(output, { [key]: source[key] });
                } else {
                    output[key] = mergeDeep(target[key], source[key]);
                }
            } else {
                Object.assign(output, { [key]: source[key] });
            }
        });
    }
    
    return output;
}

function isObject(item) {
    return item && typeof item === 'object' && !Array.isArray(item);
}