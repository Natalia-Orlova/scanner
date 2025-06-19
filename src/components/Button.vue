<script setup>
const props = defineProps({
    label: {
        type: String,
        default: "Button",
    },
    color: {
        type: String,
        default: "primary",
    },
    outlined: {
        type: Boolean,
        required: false,
    },
    disabled: {
        type: Boolean,
        required: false,
    },
    icon: {
        type: String,
        required: false,
    },
    size: {
        type: String,
        default: "normal",
    },
    submit: {
        type: Boolean,
        required: false,
    },
    back: {
        type: Boolean,
        required: false,
    },
    active: {
        type: Boolean,
        required: false,
    },
});

const emit = defineEmits(["click"]);

const clickOnButton = () => {
    emit("click");
};
</script>

<template>
    <button
        :type="props.submit ? 'submit' : 'button'"
        :class="[
      'btn',
      `btn_${color}`,
      { btn_outlined: outlined },
      { btn_icon: icon },
      { btn_large: size === 'large' },
      { btn_submit: submit },
      { btn_back: back },
      { btn_active: active }
    ]"
        :disabled="disabled"
        @click="clickOnButton"
    >
        <img
            v-if="icon"
            :src="`/app/icons/${icon}.svg`"
            alt="icon"
        />
        <span v-else>{{ label }}</span>
    </button>
</template>

<style lang="scss">
.btn {
    border-radius: 11vw;
    padding: 6.36vw 4.83vw;
    font-size: 4.5vw;
    font-family: RoobertBold, sans-serif;
    margin-bottom: 2vw;
    display: flex;
    justify-content: center;
    align-items: center;
    width: 100%;
    cursor: pointer;
    &_primary {
        background-color: var(--primary);
        color: var(--second-color);
        border: 1px solid var(--primary);
    }
    &_second {
        background-color: #f8f8f8;
        color: var(--primary);
    }
    &_icon {
        width: auto;
        border: none;
        border-radius: 50%;
        padding: 4.58vw;
        margin-bottom: 0;
        & img {
            width: 6vw;
            height: 6vw;
            opacity: 0.3;
            transition: opacity 0.2s;
        }
    }
    &_active {
        & img {
            opacity: 1;
        }
    }
    &_large img {
        width: 8vw;
        height: 8vw;
        opacity: 1;
    }
    &_back {
        background-color: #ebebeb;
        & img {
            opacity: 1;
        }
    }
    &:disabled {
        opacity: 0.6;
        cursor: default;
    }
    &_outlined {
        background-color: transparent;
        color: var(--primary);
        border: 1px solid #e9e9e9;
    }
    &_submit {
        align-items: flex-end;
        gap: 3vw;
        &::before {
            content: "";
            width: 6vw;
            height: 6vw;
            background-image: url(/icons/check.svg);
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
        }
    }
}
@media (min-width: 540px) or (orientation: landscape) {
    .btn {
        border-radius: 44px;
        padding: 25px 20px;
        font-size: 18px;
        margin-bottom: 8px;
        &_icon {
            padding: 18px;
            margin-bottom: 0;
            & img {
                width: 24px;
                height: 24px;
            }
        }
        &_large img {
            width: 32px;
            height: 32px;
        }
        &_submit {
            gap: 12px;
            &::before {
                width: 24px;
                height: 24px;
            }
        }
    }
}
</style>
