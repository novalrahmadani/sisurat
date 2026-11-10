import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\PublicInternshipController::show
 * @see app/Http/Controllers/PublicInternshipController.php:26
 * @route '/absensi-magang'
 */
export const show = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/absensi-magang',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PublicInternshipController::show
 * @see app/Http/Controllers/PublicInternshipController.php:26
 * @route '/absensi-magang'
 */
show.url = (options?: RouteQueryOptions) => {
    return show.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PublicInternshipController::show
 * @see app/Http/Controllers/PublicInternshipController.php:26
 * @route '/absensi-magang'
 */
show.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\PublicInternshipController::show
 * @see app/Http/Controllers/PublicInternshipController.php:26
 * @route '/absensi-magang'
 */
show.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\PublicInternshipController::show
 * @see app/Http/Controllers/PublicInternshipController.php:26
 * @route '/absensi-magang'
 */
    const showForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\PublicInternshipController::show
 * @see app/Http/Controllers/PublicInternshipController.php:26
 * @route '/absensi-magang'
 */
        showForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\PublicInternshipController::show
 * @see app/Http/Controllers/PublicInternshipController.php:26
 * @route '/absensi-magang'
 */
        showForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    show.form = showForm
/**
* @see \App\Http\Controllers\PublicInternshipController::session
 * @see app/Http/Controllers/PublicInternshipController.php:31
 * @route '/absensi-magang/sesi'
 */
export const session = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: session.url(options),
    method: 'get',
})

session.definition = {
    methods: ["get","head"],
    url: '/absensi-magang/sesi',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PublicInternshipController::session
 * @see app/Http/Controllers/PublicInternshipController.php:31
 * @route '/absensi-magang/sesi'
 */
session.url = (options?: RouteQueryOptions) => {
    return session.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PublicInternshipController::session
 * @see app/Http/Controllers/PublicInternshipController.php:31
 * @route '/absensi-magang/sesi'
 */
session.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: session.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\PublicInternshipController::session
 * @see app/Http/Controllers/PublicInternshipController.php:31
 * @route '/absensi-magang/sesi'
 */
session.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: session.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\PublicInternshipController::session
 * @see app/Http/Controllers/PublicInternshipController.php:31
 * @route '/absensi-magang/sesi'
 */
    const sessionForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: session.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\PublicInternshipController::session
 * @see app/Http/Controllers/PublicInternshipController.php:31
 * @route '/absensi-magang/sesi'
 */
        sessionForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: session.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\PublicInternshipController::session
 * @see app/Http/Controllers/PublicInternshipController.php:31
 * @route '/absensi-magang/sesi'
 */
        sessionForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: session.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    session.form = sessionForm
/**
* @see \App\Http\Controllers\PublicInternshipController::verifyPin
 * @see app/Http/Controllers/PublicInternshipController.php:120
 * @route '/absensi-magang/masuk'
 */
export const verifyPin = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: verifyPin.url(options),
    method: 'post',
})

verifyPin.definition = {
    methods: ["post"],
    url: '/absensi-magang/masuk',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\PublicInternshipController::verifyPin
 * @see app/Http/Controllers/PublicInternshipController.php:120
 * @route '/absensi-magang/masuk'
 */
verifyPin.url = (options?: RouteQueryOptions) => {
    return verifyPin.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PublicInternshipController::verifyPin
 * @see app/Http/Controllers/PublicInternshipController.php:120
 * @route '/absensi-magang/masuk'
 */
verifyPin.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: verifyPin.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\PublicInternshipController::verifyPin
 * @see app/Http/Controllers/PublicInternshipController.php:120
 * @route '/absensi-magang/masuk'
 */
    const verifyPinForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: verifyPin.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\PublicInternshipController::verifyPin
 * @see app/Http/Controllers/PublicInternshipController.php:120
 * @route '/absensi-magang/masuk'
 */
        verifyPinForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: verifyPin.url(options),
            method: 'post',
        })
    
    verifyPin.form = verifyPinForm
/**
* @see \App\Http\Controllers\PublicInternshipController::issuePin
 * @see app/Http/Controllers/PublicInternshipController.php:155
 * @route '/absensi-magang/pin'
 */
export const issuePin = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: issuePin.url(options),
    method: 'post',
})

issuePin.definition = {
    methods: ["post"],
    url: '/absensi-magang/pin',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\PublicInternshipController::issuePin
 * @see app/Http/Controllers/PublicInternshipController.php:155
 * @route '/absensi-magang/pin'
 */
issuePin.url = (options?: RouteQueryOptions) => {
    return issuePin.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PublicInternshipController::issuePin
 * @see app/Http/Controllers/PublicInternshipController.php:155
 * @route '/absensi-magang/pin'
 */
issuePin.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: issuePin.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\PublicInternshipController::issuePin
 * @see app/Http/Controllers/PublicInternshipController.php:155
 * @route '/absensi-magang/pin'
 */
    const issuePinForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: issuePin.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\PublicInternshipController::issuePin
 * @see app/Http/Controllers/PublicInternshipController.php:155
 * @route '/absensi-magang/pin'
 */
        issuePinForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: issuePin.url(options),
            method: 'post',
        })
    
    issuePin.form = issuePinForm
/**
* @see \App\Http\Controllers\PublicInternshipController::register
 * @see app/Http/Controllers/PublicInternshipController.php:62
 * @route '/absensi-magang/daftar'
 */
export const register = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: register.url(options),
    method: 'post',
})

register.definition = {
    methods: ["post"],
    url: '/absensi-magang/daftar',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\PublicInternshipController::register
 * @see app/Http/Controllers/PublicInternshipController.php:62
 * @route '/absensi-magang/daftar'
 */
register.url = (options?: RouteQueryOptions) => {
    return register.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PublicInternshipController::register
 * @see app/Http/Controllers/PublicInternshipController.php:62
 * @route '/absensi-magang/daftar'
 */
register.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: register.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\PublicInternshipController::register
 * @see app/Http/Controllers/PublicInternshipController.php:62
 * @route '/absensi-magang/daftar'
 */
    const registerForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: register.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\PublicInternshipController::register
 * @see app/Http/Controllers/PublicInternshipController.php:62
 * @route '/absensi-magang/daftar'
 */
        registerForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: register.url(options),
            method: 'post',
        })
    
    register.form = registerForm
/**
* @see \App\Http\Controllers\PublicInternshipController::recovery
 * @see app/Http/Controllers/PublicInternshipController.php:83
 * @route '/absensi-magang/akses/{token}'
 */
export const recovery = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: recovery.url(args, options),
    method: 'get',
})

recovery.definition = {
    methods: ["get","head"],
    url: '/absensi-magang/akses/{token}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PublicInternshipController::recovery
 * @see app/Http/Controllers/PublicInternshipController.php:83
 * @route '/absensi-magang/akses/{token}'
 */
recovery.url = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { token: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    token: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        token: args.token,
                }

    return recovery.definition.url
            .replace('{token}', parsedArgs.token.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PublicInternshipController::recovery
 * @see app/Http/Controllers/PublicInternshipController.php:83
 * @route '/absensi-magang/akses/{token}'
 */
recovery.get = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: recovery.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\PublicInternshipController::recovery
 * @see app/Http/Controllers/PublicInternshipController.php:83
 * @route '/absensi-magang/akses/{token}'
 */
recovery.head = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: recovery.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\PublicInternshipController::recovery
 * @see app/Http/Controllers/PublicInternshipController.php:83
 * @route '/absensi-magang/akses/{token}'
 */
    const recoveryForm = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: recovery.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\PublicInternshipController::recovery
 * @see app/Http/Controllers/PublicInternshipController.php:83
 * @route '/absensi-magang/akses/{token}'
 */
        recoveryForm.get = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: recovery.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\PublicInternshipController::recovery
 * @see app/Http/Controllers/PublicInternshipController.php:83
 * @route '/absensi-magang/akses/{token}'
 */
        recoveryForm.head = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: recovery.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    recovery.form = recoveryForm
/**
* @see \App\Http\Controllers\PublicInternshipController::recover
 * @see app/Http/Controllers/PublicInternshipController.php:90
 * @route '/absensi-magang/akses/{token}'
 */
export const recover = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: recover.url(args, options),
    method: 'post',
})

recover.definition = {
    methods: ["post"],
    url: '/absensi-magang/akses/{token}',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\PublicInternshipController::recover
 * @see app/Http/Controllers/PublicInternshipController.php:90
 * @route '/absensi-magang/akses/{token}'
 */
recover.url = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { token: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    token: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        token: args.token,
                }

    return recover.definition.url
            .replace('{token}', parsedArgs.token.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PublicInternshipController::recover
 * @see app/Http/Controllers/PublicInternshipController.php:90
 * @route '/absensi-magang/akses/{token}'
 */
recover.post = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: recover.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\PublicInternshipController::recover
 * @see app/Http/Controllers/PublicInternshipController.php:90
 * @route '/absensi-magang/akses/{token}'
 */
    const recoverForm = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: recover.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\PublicInternshipController::recover
 * @see app/Http/Controllers/PublicInternshipController.php:90
 * @route '/absensi-magang/akses/{token}'
 */
        recoverForm.post = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: recover.url(args, options),
            method: 'post',
        })
    
    recover.form = recoverForm
/**
* @see \App\Http\Controllers\PublicInternshipController::verify
 * @see app/Http/Controllers/PublicInternshipController.php:104
 * @route '/absensi-magang/periksa'
 */
export const verify = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: verify.url(options),
    method: 'post',
})

verify.definition = {
    methods: ["post"],
    url: '/absensi-magang/periksa',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\PublicInternshipController::verify
 * @see app/Http/Controllers/PublicInternshipController.php:104
 * @route '/absensi-magang/periksa'
 */
verify.url = (options?: RouteQueryOptions) => {
    return verify.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PublicInternshipController::verify
 * @see app/Http/Controllers/PublicInternshipController.php:104
 * @route '/absensi-magang/periksa'
 */
verify.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: verify.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\PublicInternshipController::verify
 * @see app/Http/Controllers/PublicInternshipController.php:104
 * @route '/absensi-magang/periksa'
 */
    const verifyForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: verify.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\PublicInternshipController::verify
 * @see app/Http/Controllers/PublicInternshipController.php:104
 * @route '/absensi-magang/periksa'
 */
        verifyForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: verify.url(options),
            method: 'post',
        })
    
    verify.form = verifyForm
/**
* @see \App\Http\Controllers\PublicInternshipController::submit
 * @see app/Http/Controllers/PublicInternshipController.php:175
 * @route '/absensi-magang'
 */
export const submit = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: submit.url(options),
    method: 'post',
})

submit.definition = {
    methods: ["post"],
    url: '/absensi-magang',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\PublicInternshipController::submit
 * @see app/Http/Controllers/PublicInternshipController.php:175
 * @route '/absensi-magang'
 */
submit.url = (options?: RouteQueryOptions) => {
    return submit.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PublicInternshipController::submit
 * @see app/Http/Controllers/PublicInternshipController.php:175
 * @route '/absensi-magang'
 */
submit.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: submit.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\PublicInternshipController::submit
 * @see app/Http/Controllers/PublicInternshipController.php:175
 * @route '/absensi-magang'
 */
    const submitForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: submit.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\PublicInternshipController::submit
 * @see app/Http/Controllers/PublicInternshipController.php:175
 * @route '/absensi-magang'
 */
        submitForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: submit.url(options),
            method: 'post',
        })
    
    submit.form = submitForm
/**
* @see \App\Http\Controllers\PublicInternshipController::leave
 * @see app/Http/Controllers/PublicInternshipController.php:213
 * @route '/absensi-magang/ganti-peserta'
 */
export const leave = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: leave.url(options),
    method: 'post',
})

leave.definition = {
    methods: ["post"],
    url: '/absensi-magang/ganti-peserta',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\PublicInternshipController::leave
 * @see app/Http/Controllers/PublicInternshipController.php:213
 * @route '/absensi-magang/ganti-peserta'
 */
leave.url = (options?: RouteQueryOptions) => {
    return leave.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PublicInternshipController::leave
 * @see app/Http/Controllers/PublicInternshipController.php:213
 * @route '/absensi-magang/ganti-peserta'
 */
leave.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: leave.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\PublicInternshipController::leave
 * @see app/Http/Controllers/PublicInternshipController.php:213
 * @route '/absensi-magang/ganti-peserta'
 */
    const leaveForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: leave.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\PublicInternshipController::leave
 * @see app/Http/Controllers/PublicInternshipController.php:213
 * @route '/absensi-magang/ganti-peserta'
 */
        leaveForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: leave.url(options),
            method: 'post',
        })
    
    leave.form = leaveForm
const PublicInternshipController = { show, session, verifyPin, issuePin, register, recovery, recover, verify, submit, leave }

export default PublicInternshipController
