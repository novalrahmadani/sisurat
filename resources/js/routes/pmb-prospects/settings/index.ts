import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\PmbProspectController::update
 * @see app/Http/Controllers/PmbProspectController.php:65
 * @route '/pmb-prospects/settings'
 */
export const update = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/pmb-prospects/settings',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\PmbProspectController::update
 * @see app/Http/Controllers/PmbProspectController.php:65
 * @route '/pmb-prospects/settings'
 */
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PmbProspectController::update
 * @see app/Http/Controllers/PmbProspectController.php:65
 * @route '/pmb-prospects/settings'
 */
update.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\PmbProspectController::update
 * @see app/Http/Controllers/PmbProspectController.php:65
 * @route '/pmb-prospects/settings'
 */
    const updateForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url({
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\PmbProspectController::update
 * @see app/Http/Controllers/PmbProspectController.php:65
 * @route '/pmb-prospects/settings'
 */
        updateForm.patch = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    update.form = updateForm
const settings = {
    update: Object.assign(update, update),
}

export default settings