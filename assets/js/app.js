document.querySelectorAll('[data-video-source]').forEach((source) => {
    const form = source.closest('form');
    const type = form.querySelector('[name="type"]');
    const youtube = form.querySelector('[name="youtube_url"]');
    const upload = form.querySelector('[name="attachment"]');
    const sync = () => {
        const video = type.value === 'video';
        const fromYoutube = source.value === 'youtube';
        form.querySelector('[data-video-source-field]').hidden = !video;
        form.querySelector('[data-video-youtube-field]').hidden = video && !fromYoutube;
        form.querySelector('[data-video-upload-field]').hidden = video && fromYoutube;
        form.querySelector('[data-video-upload-hint]').hidden = !video;
        youtube.disabled = video && !fromYoutube;
        youtube.required = video && fromYoutube;
        upload.disabled = video && fromYoutube;
        upload.accept = video ? '.mp4,.webm,video/mp4,video/webm' : '';
        upload.setCustomValidity(video && upload.files[0]?.size > 10000000
            ? 'Video maksimal 10 MB. Gunakan YouTube untuk video lebih besar.' : '');
    };
    type.addEventListener('change', sync);
    source.addEventListener('change', sync);
    upload.addEventListener('change', sync);
    sync();
});

document.querySelectorAll('[data-role-switch]').forEach((switcher) => {
    const buttons = switcher.querySelectorAll('[data-role-value]');
    const input = switcher.parentElement.querySelector('[data-role-input]');
    const loginLabel = switcher.parentElement.querySelector('[data-auth-login-label]');
    const loginInput = switcher.parentElement.querySelector('[data-auth-login-id]');
    const roleLabel = switcher.parentElement.querySelector('[data-auth-role-label]');
    const roleCodeInput = switcher.parentElement.querySelector('[data-auth-role-code]');
    const forgotPasswordLink = switcher.parentElement.querySelector('[data-forgot-password-link]');

    const syncRoleUi = (role) => {
        if (loginLabel) {
            loginLabel.textContent = role === 'guru' ? 'NIP' : 'NIS';
        }

        if (loginInput) {
            const nextPlaceholder = role === 'guru'
                ? loginInput.dataset.placeholderGuru
                : loginInput.dataset.placeholderSiswa;
            if (nextPlaceholder) {
                loginInput.placeholder = nextPlaceholder;
            }
        }

        if (roleLabel) {
            roleLabel.textContent = role === 'guru' ? 'Kode Akses Guru' : 'Kode Akses Siswa';
        }

        if (roleCodeInput) {
            const nextPlaceholder = role === 'guru'
                ? roleCodeInput.dataset.roleCodePlaceholderGuru
                : roleCodeInput.dataset.roleCodePlaceholderSiswa;
            if (nextPlaceholder) {
                roleCodeInput.placeholder = nextPlaceholder;
            }
        }

        if (forgotPasswordLink) {
            forgotPasswordLink.href = `forgot-password.php?role=${encodeURIComponent(role)}`;
        }
    };

    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            buttons.forEach((item) => item.classList.remove('is-active'));
            button.classList.add('is-active');

            if (input) {
                input.value = button.dataset.roleValue || 'siswa';
                syncRoleUi(input.value);
            }
        });
    });

    if (input) {
        syncRoleUi(input.value || 'siswa');
    }
});

document.querySelectorAll('[data-demo-login-id], [data-demo-email]').forEach((card) => {
    card.addEventListener('click', () => {
        const loginField = document.querySelector('[data-auth-login-id]') || document.querySelector('[data-auth-email]');
        const passwordField = document.querySelector('[data-auth-password]');
        const roleCodeField = document.querySelector('[data-auth-role-code]');
        const roleInput = document.querySelector('[data-role-input]');

        if (loginField) {
            loginField.value = card.dataset.demoLoginId || card.dataset.demoEmail || '';
        }

        if (passwordField) {
            passwordField.value = card.dataset.demoPassword || '';
        }

        if (roleCodeField) {
            roleCodeField.value = card.dataset.demoRoleCode || '';
        }

        if (roleInput && card.dataset.demoRole) {
            roleInput.value = card.dataset.demoRole;
            const activeButton = document.querySelector(`[data-role-value="${card.dataset.demoRole}"]`);
            if (activeButton) {
                const switcher = activeButton.closest('[data-role-switch]');
                if (switcher) {
                    switcher.querySelectorAll('[data-role-value]').forEach((button) => button.classList.remove('is-active'));
                    activeButton.classList.add('is-active');
                }
            }
        }
    });
});

document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const field = button.parentElement?.querySelector('input');
        if (!field) {
            return;
        }

        field.type = field.type === 'password' ? 'text' : 'password';
        button.classList.toggle('is-visible', field.type === 'text');
        button.innerHTML = field.type === 'text'
            ? (button.dataset.eyeOff || '')
            : (button.dataset.eyeOn || '');
    });
});

document.querySelectorAll('[data-custom-select]').forEach((selectRoot) => {
    const trigger = selectRoot.querySelector('[data-custom-select-trigger]');
    const menu = selectRoot.querySelector('[data-custom-select-menu]');
    const valueInput = selectRoot.querySelector('[data-custom-select-value]');
    const label = selectRoot.querySelector('[data-custom-select-label]');
    const options = selectRoot.querySelectorAll('[data-custom-select-option]');

    if (!trigger || !menu || !valueInput || !label) {
        return;
    }

    const closeMenu = () => {
        selectRoot.classList.remove('is-open');
        trigger.setAttribute('aria-expanded', 'false');
    };

    const openMenu = () => {
        selectRoot.classList.add('is-open');
        trigger.setAttribute('aria-expanded', 'true');
    };

    trigger.addEventListener('click', () => {
        if (selectRoot.classList.contains('is-open')) {
            closeMenu();
        } else {
            openMenu();
        }
    });

    options.forEach((option) => {
        if (option.dataset.value === valueInput.value) {
            option.classList.add('is-active');
        }

        option.addEventListener('click', () => {
            const nextValue = option.dataset.value || '';
            const nextLabel = option.dataset.label || nextValue;

            valueInput.value = nextValue;
            label.textContent = nextLabel;

            options.forEach((item) => item.classList.toggle('is-active', item === option));
            closeMenu();
        });
    });

    document.addEventListener('click', (event) => {
        if (!selectRoot.contains(event.target)) {
            closeMenu();
        }
    });

    window.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeMenu();
        }
    });
});

const revealTargets = document.querySelectorAll('.feature-card, .case-card, .module-card, .panel-card, .knowledge-card, .summary-card');

if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12 });

    revealTargets.forEach((target) => observer.observe(target));
}

(() => {
    const guruPage = document.querySelector('main.guru-page') || document.querySelector('.guru-page');
    const sidebar = guruPage?.querySelector('.guru-sidebar');
    const toggleButton = guruPage?.querySelector('[data-guru-sidebar-toggle]');
    const overlay = guruPage?.querySelector('[data-guru-sidebar-overlay]');

    if (!guruPage || !sidebar || !toggleButton || !overlay) {
        return;
    }

    const mobileMedia = window.matchMedia('(max-width: 1080px)');
    const content = guruPage.querySelector('.guru-content, .student-content');

    const closeSidebar = () => {
        guruPage.classList.remove('is-sidebar-open');
        toggleButton.setAttribute('aria-expanded', 'false');
        toggleButton.setAttribute('aria-label', 'Buka navigasi');
        document.documentElement.classList.remove('is-navigation-open');
        sidebar.inert = mobileMedia.matches;
        if (content) content.inert = false;
    };

    const openSidebar = () => {
        guruPage.classList.add('is-sidebar-open');
        toggleButton.setAttribute('aria-expanded', 'true');
        toggleButton.setAttribute('aria-label', 'Tutup navigasi');
        document.documentElement.classList.add('is-navigation-open');
        sidebar.inert = false;
        if (content) content.inert = true;
    };

    toggleButton.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();

        if (!mobileMedia.matches) {
            return;
        }

        if (guruPage.classList.contains('is-sidebar-open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    });

    overlay.addEventListener('click', () => {
        closeSidebar();
        toggleButton.focus();
    });

    sidebar.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            if (mobileMedia.matches) {
                closeSidebar();
            }
        });
    });

    window.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && guruPage.classList.contains('is-sidebar-open')) {
            closeSidebar();
            toggleButton.focus();
        }
        if (event.key === 'Tab' && guruPage.classList.contains('is-sidebar-open')) {
            const links = Array.from(sidebar.querySelectorAll('a, button')).filter(item => !item.disabled);
            const last = links[links.length - 1] || toggleButton;
            if (event.shiftKey && document.activeElement === toggleButton) {
                event.preventDefault(); last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault(); toggleButton.focus();
            }
        }
    });

    const handleViewportChange = (event) => {
        closeSidebar();
    };

    if (typeof mobileMedia.addEventListener === 'function') {
        mobileMedia.addEventListener('change', handleViewportChange);
    } else {
        mobileMedia.addListener(handleViewportChange);
    }
    closeSidebar();
})();

(() => {
    const featureArts = document.querySelectorAll('.feature-network-card .feature-art');
    if (!featureArts.length) {
        return;
    }

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    featureArts.forEach((art) => {
        const reset = () => {
            art.style.setProperty('--tilt-x', '0deg');
            art.style.setProperty('--tilt-y', '0deg');
        };

        art.addEventListener('pointermove', (event) => {
            const rect = art.getBoundingClientRect();
            const x = (event.clientX - rect.left) / rect.width;
            const y = (event.clientY - rect.top) / rect.height;

            const tiltY = (x - 0.5) * 10;
            const tiltX = (0.5 - y) * 8;

            art.style.setProperty('--tilt-x', `${tiltX.toFixed(2)}deg`);
            art.style.setProperty('--tilt-y', `${tiltY.toFixed(2)}deg`);
        });

        art.addEventListener('pointerleave', reset);
        art.addEventListener('pointercancel', reset);
    });
})();

(() => {
    const classPickerPage = document.querySelector('.class-picker-page');
    if (!classPickerPage) {
        return;
    }

    classPickerPage.querySelectorAll('[data-class-remove-confirm]').forEach((button) => {
        button.addEventListener('click', (event) => {
            const isConfirmed = window.confirm('Yakin ingin menghapus kelas ini dari akun Anda?');
            if (!isConfirmed) {
                event.preventDefault();
            }
        });
    });
})();

(() => {
    const quizRunner = document.querySelector('.siswa-quiz-runner-card');
    if (!quizRunner) {
        return;
    }

    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            window.location.reload();
        }
    });

    const form = quizRunner.querySelector('.siswa-quiz-form');
    const optionInputs = quizRunner.querySelectorAll('.siswa-quiz-option input[type="radio"]');
    const submitButton = quizRunner.querySelector('[data-quiz-submit]');
    const liveFeedback = quizRunner.querySelector('[data-quiz-live-feedback]');
    const timerCard = quizRunner.querySelector('[data-quiz-timer]');
    const timerLabel = quizRunner.querySelector('[data-quiz-timer-label]');
    const correctOption = (form?.dataset.correctOption || '').toLowerCase();
    const timeoutToken = form?.dataset.quizTimeoutToken || '__timeout__';
    const autoNextDelay = Number(form?.dataset.autoNextDelay || 2200);
    const defaultSubmitLabel = submitButton ? submitButton.textContent.trim() : 'Cek & Lanjut';
    const timerLimit = Number(timerCard?.dataset.quizTimeLimit || 0);
    let timerRemaining = Number(timerCard?.dataset.quizTimeRemaining || 0);
    let timerIntervalId = null;

    const clearTimer = () => {
        if (timerIntervalId !== null) {
            window.clearInterval(timerIntervalId);
            timerIntervalId = null;
        }
    };

    const formatSeconds = (seconds) => {
        const safeSeconds = Math.max(0, Number(seconds) || 0);
        const minutes = Math.floor(safeSeconds / 60);
        const remainingSeconds = safeSeconds % 60;
        return `${String(minutes).padStart(2, '0')}:${String(remainingSeconds).padStart(2, '0')}`;
    };

    const syncTimerUi = () => {
        if (!timerCard || !timerLabel || timerLimit <= 0) {
            return;
        }

        timerLabel.textContent = formatSeconds(timerRemaining);
        timerCard.classList.toggle('is-warning', timerRemaining > 0 && timerRemaining <= 10);
        timerCard.classList.toggle('is-critical', timerRemaining === 0);
    };

    const submitAsTimeout = () => {
        if (!form || form.dataset.revealState === 'pending') {
            return;
        }

        clearTimer();
        form.dataset.revealState = 'pending';

        const existingSelectedOptionField = form.querySelector('input[data-auto-selected-option="1"]');
        if (existingSelectedOptionField) {
            existingSelectedOptionField.remove();
        }

        const timeoutField = document.createElement('input');
        timeoutField.type = 'hidden';
        timeoutField.name = 'selected_option';
        timeoutField.value = timeoutToken;
        timeoutField.setAttribute('data-auto-selected-option', '1');
        form.appendChild(timeoutField);

        optionInputs.forEach((input) => {
            input.required = false;
            input.disabled = true;
        });

        quizRunner.querySelectorAll('.siswa-quiz-option').forEach((card) => {
            card.classList.remove('is-selected');
            card.classList.add('is-locked');
        });

        if (liveFeedback) {
            liveFeedback.classList.add('is-visible', 'is-wrong');
            liveFeedback.classList.remove('is-correct');
            liveFeedback.textContent = 'Waktu habis. Sistem lanjut ke soal berikutnya.';
        }

        if (submitButton) {
            submitButton.textContent = 'Waktu habis...';
            submitButton.disabled = true;
        }

        window.setTimeout(() => {
            form.submit();
        }, 350);
    };

    const startTimer = () => {
        if (!timerCard || timerLimit <= 0) {
            return;
        }

        timerRemaining = Math.max(0, timerRemaining);
        syncTimerUi();
        if (timerRemaining <= 0) {
            submitAsTimeout();
            return;
        }

        timerIntervalId = window.setInterval(() => {
            if (form?.dataset.revealState === 'pending') {
                clearTimer();
                return;
            }

            timerRemaining = Math.max(0, timerRemaining - 1);
            syncTimerUi();
            if (timerRemaining <= 0) {
                submitAsTimeout();
            }
        }, 1000);
    };

    const updateSubmitLabel = () => {
        if (!submitButton) {
            return;
        }

        const selected = quizRunner.querySelector('.siswa-quiz-option input[type="radio"]:checked');
        if (!selected) {
            submitButton.textContent = 'Pilih Jawaban Dulu';
            submitButton.disabled = true;
            return;
        }

        if (submitButton.textContent.trim() === 'Pilih Jawaban Dulu') {
            submitButton.textContent = defaultSubmitLabel;
        }
        submitButton.disabled = false;
    };

    optionInputs.forEach((input) => {
        const optionCard = input.closest('.siswa-quiz-option');
        if (!optionCard) {
            return;
        }

        input.addEventListener('change', () => {
            quizRunner.querySelectorAll('.siswa-quiz-option').forEach((card) => card.classList.remove('is-selected'));
            optionCard.classList.add('is-selected');
            updateSubmitLabel();
        });

        optionCard.addEventListener('pointerdown', () => {
            optionCard.classList.add('is-pressed');
        });

        optionCard.addEventListener('pointerup', () => {
            optionCard.classList.remove('is-pressed');
        });

        optionCard.addEventListener('pointerleave', () => {
            optionCard.classList.remove('is-pressed');
        });
    });

    if (form && submitButton) {
        form.addEventListener('submit', (event) => {
            if (form.dataset.revealState === 'pending') {
                event.preventDefault();
                return;
            }

            const selectedInput = quizRunner.querySelector('.siswa-quiz-option input[type="radio"]:checked');
            if (!selectedInput || !correctOption) {
                return;
            }

            event.preventDefault();
            clearTimer();
            form.dataset.revealState = 'pending';

            const selectedOption = (selectedInput.value || '').toLowerCase();
            const isCorrect = selectedOption === correctOption;

            // Radios are disabled during reveal, so mirror selected value in hidden field.
            const existingSelectedOptionField = form.querySelector('input[data-auto-selected-option="1"]');
            if (existingSelectedOptionField) {
                existingSelectedOptionField.remove();
            }
            const selectedOptionField = document.createElement('input');
            selectedOptionField.type = 'hidden';
            selectedOptionField.name = 'selected_option';
            selectedOptionField.value = selectedOption;
            selectedOptionField.setAttribute('data-auto-selected-option', '1');
            form.appendChild(selectedOptionField);

            quizRunner.querySelectorAll('.siswa-quiz-option').forEach((card) => {
                const input = card.querySelector('input[type="radio"]');
                if (!input) {
                    return;
                }

                const optionValue = (input.value || '').toLowerCase();
                card.classList.remove('is-selected');
                card.classList.add('is-locked');

                if (optionValue === correctOption) {
                    card.classList.add('is-reveal-correct');
                }

                if (optionValue === selectedOption && selectedOption !== correctOption) {
                    card.classList.add('is-reveal-wrong');
                }

                input.disabled = true;
            });

            if (liveFeedback) {
                liveFeedback.classList.add('is-visible');
                liveFeedback.classList.toggle('is-correct', isCorrect);
                liveFeedback.classList.toggle('is-wrong', !isCorrect);
                liveFeedback.textContent = isCorrect
                    ? 'Jawaban benar. Lanjut ke soal berikutnya...'
                    : 'Jawaban salah. Jawaban benar sudah ditandai hijau.';
            }

            submitButton.textContent = 'Lanjut otomatis...';
            submitButton.disabled = true;

            window.setTimeout(() => {
                form.submit();
            }, autoNextDelay);
        });
    }

    updateSubmitLabel();
    startTimer();
})();

// Preview optional PG question images before the teacher saves the form.
document.querySelectorAll('[data-question-image-form]').forEach((form) => {
    const input = form.querySelector('[data-question-image-input]');
    const preview = form.querySelector('[data-question-image-preview]');
    const remove = form.querySelector('[data-question-image-remove]');
    const error = form.querySelector('[data-question-image-error]');
    if (!input || !preview || !error) return;
    let objectUrl = null;

    const updatePreview = () => {
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = null;
        input.setCustomValidity('');
        error.textContent = '';
        const file = input.files[0];
        if (file && !['image/jpeg', 'image/png', 'image/gif', 'image/webp'].includes(file.type)) {
            error.textContent = 'Pilih gambar JPG, PNG, GIF, atau WEBP.';
        } else if (file && file.size > 5 * 1024 * 1024) {
            error.textContent = 'Ukuran gambar maksimal 5 MB.';
        }
        input.setCustomValidity(error.textContent);
        if (file && !error.textContent) {
            objectUrl = URL.createObjectURL(file);
            preview.src = objectUrl;
            preview.hidden = false;
            if (remove) remove.checked = false;
        } else {
            const existing = preview.dataset.existingSrc;
            preview.hidden = !existing || !!(remove && remove.checked);
            if (!preview.hidden) preview.src = existing;
            else preview.removeAttribute('src');
        }
    };
    input.addEventListener('change', updatePreview);
    if (remove) remove.addEventListener('change', () => {
        if (remove.checked) input.value = '';
        updatePreview();
    });
    window.addEventListener('pagehide', () => {
        if (objectUrl) URL.revokeObjectURL(objectUrl);
    });
});
