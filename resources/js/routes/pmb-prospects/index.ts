import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../wayfinder'
import settings from './settings'
/**
* @see \App\Http\Controllers\PublicPmbProspectController::join
 * @see app/Http/Controllers/PublicPmbProspectController.php:18
 * @route '/pendaftaran-calon-mahasiswa'
 */
export const join = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: join.url(options),
    method: 'get',
})

join.definition = {
    methods: ["get","head"],
    url: '/pendaftaran-calon-mahasiswa',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PublicPmbProspectController::join
 * @see app/Http/Controllers/PublicPmbProspectController.php:18
 * @route '/pendaftaran-calon-mahasiswa'
 */
join.url = (options?: RouteQueryOptions) => {
    return join.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PublicPmbProspectController::join
 * @see app/Http/Controllers/PublicPmbProspectController.php:18
 * @route '/pendaftaran-calon-mahasiswa'
 */
join.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: join.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\PublicPmbProspectController::join
 * @see app/Http/Controllers/PublicPmbProspectController.php:18
 * @route '/pendaftaran-calon-mahasiswa'
 */
join.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: join.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\PublicPmbProspectController::join
 * @see app/Http/Controllers/PublicPmbProspectController.php:18
 * @route '/pendaftaran-calon-mahasiswa'
 */
    const joinForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: join.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\PublicPmbProspectController::join
 * @see app/Http/Controllers/PublicPmbProspectController.php:18
 * @route '/pendaftaran-calon-mahasiswa'
 */
        joinForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: join.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\PublicPmbProspectController::join
 * @see app/Http/Controllers/PublicPmbProspectController.php:18
 * @route '/pendaftaran-calon-mahasiswa'
 */
        joinForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: join.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    join.form = joinForm
/**
* @see \App\Http\Controllers\PublicPmbProspectController::submit
 * @see app/Http/Controllers/PublicPmbProspectController.php:35
 * @route '/pendaftaran-calon-mahasiswa'
 */
export const submit = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: submit.url(options),
    method: 'post',
})

submit.definition = {
    methods: ["post"],
    url: '/pendaftaran-calon-mahasiswa',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\PublicPmbProspectController::submit
 * @see app/Http/Controllers/PublicPmbProspectController.php:35
 * @route '/pendaftaran-calon-mahasiswa'
 */
submit.url = (options?: RouteQueryOptions) => {
    return submit.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PublicPmbProspectController::submit
 * @see app/Http/Controllers/PublicPmbProspectController.php:35
 * @route '/pendaftaran-calon-mahasiswa'
 */
submit.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: submit.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\PublicPmbProspectController::submit
 * @see app/Http/Controllers/PublicPmbProspectController.php:35
 * @route '/pendaftaran-calon-mahasiswa'
 */
    const submitForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: submit.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\PublicPmbProspectController::submit
 * @see app/Http/Controllers/PublicPmbProspectController.php:35
 * @route '/pendaftaran-calon-mahasiswa'
 */
        submitForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: submit.url(options),
            method: 'post',
        })
    
    submit.form = submitForm
/**
* @see \App\Http\Controllers\PublicFormQrController::qr
 * @see app/Http/Controllers/PublicFormQrController.php:45
 * @route '/pmb-prospects/qr'
 */
export const qr = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: qr.url(options),
    method: 'get',
})

qr.definition = {
    methods: ["get","head"],
    url: '/pmb-prospects/qr',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PublicFormQrController::qr
 * @see app/Http/Controllers/PublicFormQrController.php:45
 * @route '/pmb-prospects/qr'
 */
qr.url = (options?: RouteQueryOptions) => {
    return qr.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PublicFormQrController::qr
 * @see app/Http/Controllers/PublicFormQrController.php:45
 * @route '/pmb-prospects/qr'
 */
qr.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: qr.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\PublicFormQrController::qr
 * @see app/Http/Controllers/PublicFormQrController.php:45
 * @route '/pmb-prospects/qr'
 */
qr.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: qr.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\PublicFormQrController::qr
 * @see app/Http/Controllers/PublicFormQrController.php:45
 * @route '/pmb-prospects/qr'
 */
    const qrForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: qr.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\PublicFormQrController::qr
 * @see app/Http/Controllers/PublicFormQrController.php:45
 * @route '/pmb-prospects/qr'
 */
        qrForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: qr.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\PublicFormQrController::qr
 * @see app/Http/Controllers/PublicFormQrController.php:45
 * @route '/pmb-prospects/qr'
 */
        qrForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: qr.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    qr.form = qrForm
/**
* @see \App\Http\Controllers\PublicFormFlyerController::flyer
 * @see app/Http/Controllers/PublicFormFlyerController.php:50
 * @route '/pmb-prospects/flyer'
 */
export const flyer = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: flyer.url(options),
    method: 'get',
})

flyer.definition = {
    methods: ["get","head"],
    url: '/pmb-prospects/flyer',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PublicFormFlyerController::flyer
 * @see app/Http/Controllers/PublicFormFlyerController.php:50
 * @route '/pmb-prospects/flyer'
 */
flyer.url = (options?: RouteQueryOptions) => {
    return flyer.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PublicFormFlyerController::flyer
 * @see app/Http/Controllers/PublicFormFlyerController.php:50
 * @route '/pmb-prospects/flyer'
 */
flyer.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: flyer.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\PublicFormFlyerController::flyer
 * @see app/Http/Controllers/PublicFormFlyerController.php:50
 * @route '/pmb-prospects/flyer'
 */
flyer.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: flyer.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\PublicFormFlyerController::flyer
 * @see app/Http/Controllers/PublicFormFlyerController.php:50
 * @route '/pmb-prospects/flyer'
 */
    const flyerForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: flyer.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\PublicFormFlyerController::flyer
 * @see app/Http/Controllers/PublicFormFlyerController.php:50
 * @route '/pmb-prospects/flyer'
 */
        flyerForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: flyer.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\PublicFormFlyerController::flyer
 * @see app/Http/Controllers/PublicFormFlyerController.php:50
 * @route '/pmb-prospects/flyer'
 */
        flyerForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: flyer.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    flyer.form = flyerForm
/**
* @see \App\Http\Controllers\PmbProspectController::pdf
 * @see app/Http/Controllers/PmbProspectController.php:81
 * @route '/pmb-prospects/pdf'
 */
export const pdf = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: pdf.url(options),
    method: 'get',
})

pdf.definition = {
    methods: ["get","head"],
    url: '/pmb-prospects/pdf',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PmbProspectController::pdf
 * @see app/Http/Controllers/PmbProspectController.php:81
 * @route '/pmb-prospects/pdf'
 */
pdf.url = (options?: RouteQueryOptions) => {
    return pdf.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PmbProspectController::pdf
 * @see app/Http/Controllers/PmbProspectController.php:81
 * @route '/pmb-prospects/pdf'
 */
pdf.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: pdf.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\PmbProspectController::pdf
 * @see app/Http/Controllers/PmbProspectController.php:81
 * @route '/pmb-prospects/pdf'
 */
pdf.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: pdf.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\PmbProspectController::pdf
 * @see app/Http/Controllers/PmbProspectController.php:81
 * @route '/pmb-prospects/pdf'
 */
    const pdfForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: pdf.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\PmbProspectController::pdf
 * @see app/Http/Controllers/PmbProspectController.php:81
 * @route '/pmb-prospects/pdf'
 */
        pdfForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: pdf.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\PmbProspectController::pdf
 * @see app/Http/Controllers/PmbProspectController.php:81
 * @route '/pmb-prospects/pdf'
 */
        pdfForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: pdf.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    pdf.form = pdfForm
/**
* @see \App\Http\Controllers\PmbProspectController::index
 * @see app/Http/Controllers/PmbProspectController.php:23
 * @route '/pmb-prospects'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/pmb-prospects',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PmbProspectController::index
 * @see app/Http/Controllers/PmbProspectController.php:23
 * @route '/pmb-prospects'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PmbProspectController::index
 * @see app/Http/Controllers/PmbProspectController.php:23
 * @route '/pmb-prospects'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\PmbProspectController::index
 * @see app/Http/Controllers/PmbProspectController.php:23
 * @route '/pmb-prospects'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\PmbProspectController::index
 * @see app/Http/Controllers/PmbProspectController.php:23
 * @route '/pmb-prospects'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\PmbProspectController::index
 * @see app/Http/Controllers/PmbProspectController.php:23
 * @route '/pmb-prospects'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\PmbProspectController::index
 * @see app/Http/Controllers/PmbProspectController.php:23
 * @route '/pmb-prospects'
 */
        indexForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    index.form = indexForm
const pmbProspects = {
    join: Object.assign(join, join),
submit: Object.assign(submit, submit),
qr: Object.assign(qr, qr),
flyer: Object.assign(flyer, flyer),
pdf: Object.assign(pdf, pdf),
settings: Object.assign(settings, settings),
index: Object.assign(index, index),
}

export default pmbProspects
