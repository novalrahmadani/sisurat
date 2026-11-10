import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
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
* @see \App\Http\Controllers\PmbProspectController::updateSettings
 * @see app/Http/Controllers/PmbProspectController.php:65
 * @route '/pmb-prospects/settings'
 */
export const updateSettings = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: updateSettings.url(options),
    method: 'patch',
})

updateSettings.definition = {
    methods: ["patch"],
    url: '/pmb-prospects/settings',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\PmbProspectController::updateSettings
 * @see app/Http/Controllers/PmbProspectController.php:65
 * @route '/pmb-prospects/settings'
 */
updateSettings.url = (options?: RouteQueryOptions) => {
    return updateSettings.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PmbProspectController::updateSettings
 * @see app/Http/Controllers/PmbProspectController.php:65
 * @route '/pmb-prospects/settings'
 */
updateSettings.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: updateSettings.url(options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\PmbProspectController::updateSettings
 * @see app/Http/Controllers/PmbProspectController.php:65
 * @route '/pmb-prospects/settings'
 */
    const updateSettingsForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: updateSettings.url({
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\PmbProspectController::updateSettings
 * @see app/Http/Controllers/PmbProspectController.php:65
 * @route '/pmb-prospects/settings'
 */
        updateSettingsForm.patch = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: updateSettings.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    updateSettings.form = updateSettingsForm
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
const PmbProspectController = { pdf, updateSettings, index }

export default PmbProspectController
